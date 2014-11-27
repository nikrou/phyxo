<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014 Nicolas Roudaire              http://www.phyxo.net/ |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License version 2 as     |
// | published by the Free Software Foundation                             |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,            |
// | MA 02110-1301 USA.                                                    |
// +-----------------------------------------------------------------------+

use Behat\Behat\Context\BehatContext;
use Behat\Gherkin\Node\TableNode;
use Behat\Gherkin\Node\PyStringNode;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Post\PostFile;

use mageekguy\atoum\asserter as Atoum;

class GuzzleApiContext extends BehatContext
{
    private $assert;
    private $parameters = array();
    private $baseUrl;
    private $client;
    private $response;

    // json stuff
    private $json_data = null;
    private $json_decoded = false;

    public function __construct(array $parameters) {
        $this->assert = new Atoum\generator();

        $this->parameters = $parameters;
        $this->baseUrl = $parameters['api_base_url'];
        $this->jar = new CookieJar();

        $this->client = new Client(array('defaults' => array('cookies' => $this->jar, 'exceptions' => false)));
    }

    /**
     * @Given /^I am authenticated for api as "([^"]*)" with password "([^"]*)"$/
     */
    public function iAmAuthenticatedForApiAs($username, $password) {
        $request = $this->client->createRequest('POST', $this->baseUrl);
        $body = $request->getBody();
        $body->setField('method', 'pwg.session.login');
        $body->setField('format', 'json');
        $body->setField('username', $username);
        $body->setField('password', $password);

        $this->json_decoded = false;
        $this->response = $this->client->send($request);
    }

    /**
     * Sends HTTP request to specific relative URL.
     *
     * @param string $method request method
     * @param string $url    api method
     *
     * @When /^(?:I )?send a ([A-Z]+) request to "([^"]+)"$/
     */
    public function iSendARequest($http_method, $method) {
        $request = $this->client->createRequest($http_method, $this->baseUrl);

        if ($http_method=='GET') {
            $query = $request->getQuery();
            $query->set('method', $method);
            $query->set('format', 'json');
        } elseif ($http_method=='POST') {
            $body = $request->getBody();
            $body->setField('method', $method);
            $body->setField('format', 'json');
        }

        $this->json_decoded = false;
        $this->response = $this->client->send($request);
    }

    /**
     * Sends HTTP request to specific URL with field values from Table.
     *
     * @param string    $method request method
     * @param string    $url    relative url
     * @param TableNode $post   table of post values
     *
     * @When /^(?:I )?send a ([A-Z]+) request to "([^"]+)" with values:$/
     */
    public function iSendARequestWithValues($http_method, $method, TableNode $values) {
        $request = $this->client->createRequest($http_method, $this->baseUrl);

        $query = $request->getQuery();
        $query->set('method', $method);
        $query->set('format', 'json');

        if ($http_method=='GET') {
            foreach ($values->getRowsHash() as $key => $val) {
                if (preg_match('`^SAVED:(.*)$`', $val, $matches)) {
                    $query->set($key, $this->getMainContext()->getSubcontext('db')->getSaved($matches[1]));
                } elseif ($key=='pwg_token') {
                    $query->set(
                        'pwg_token',
                        $this->getMainContext()->getSubcontext('db')->get_pwg_token($this->getSessionId())
                    );
                } else {
                    $query->set($key, $val);
                }
            }
        } elseif ($http_method=='POST') {
            $body = $request->getBody();

            foreach ($values->getRowsHash() as $key => $val) {
                if (preg_match('`^SAVED:(.*)$`', $val, $matches)) {
                    $value = $this->getMainContext()->getSubcontext('db')->getSaved($matches[1]);

                    if ($key=='tags') { // @TODO: find a better way to add ~~ around tags id
                        $value = '~~'.$value.'~~';
                    }
                    $body->setField($key, $value);
                } elseif ($key=='pwg_token') {
                    $body->setField(
                        'pwg_token',
                        $this->getMainContext()->getSubcontext('db')->get_pwg_token($this->getSessionId())
                    );
                } elseif (preg_match('`FILE:(.*)$`', $key, $matches)) {
                    $body->addFile(new PostFile($matches[1], fopen($val, 'r')));
                } else {
                    if (preg_match('`\[(.*)]`', $val, $matches)) {
                        $val = array_map('trim', explode(',',  $matches[1]));
                        foreach ($val as &$v) {
                            if (preg_match('`^SAVED:(.*)$`', $v, $matches)) {
                                $v = $this->getMainContext()->getSubcontext('db')->getSaved($matches[1]);
                                if ($key=='tags') { // @TODO: find a better way to add ~~ around tags id
                                    $v = '~~'.$v.'~~';
                                }
                            }
                        }
                    }

                    $body->setField($key, $val);
                }
            }
        }

        $this->json_decoded = false;
        $this->response = $this->client->send($request);
    }

    /**
     * Checks that response has specific status code.
     *
     * @param string $code status code
     *
     * @Then /^(?:the )?response code should be (\d+)$/
     */
    public function theResponseCodeShouldBe($code) {
        $this->assert
            ->integer((int) $code)
            ->isEqualTo($this->response->getStatusCode());
    }

    /**
     * @Given /^the response is JSON$/
     */
    public function theResponseIsJson() {
        $this->getJson();
    }

    /**
     * @Given /^the response has property "([^"]*)"$/
     */
    public function theResponseHasProperty($property) {
        $data = $this->getJson();

        return $this->getProperty($data, $property);
    }

    /**
     * @Given /^the response has property "([^"]*)" equals to "([^"]*)"$/
     * @Given /^the response has property "([^"]*)" equals to '([^']*)'$/
     * @Then /^the response has property "([^"]*)" equals to "([^"]*)" of type (boolean)$/
     */
    public function theResponseHasPropertyEqualsTo($property, $value, $type='') {
        $data = $this->getJson();
        $value = preg_replace_callback(
            '`SAVED:([a-zA-Z0-9_-]*)`',
            function($matches) {
                return $this->getMainContext()->getSubcontext('db')->getSaved($matches[1]);
            },
            $value
        );
        if (!empty($type) && $type=='boolean') {
            $value = ($value=='true');
        }

        $this->assert
            ->variable($this->getProperty($data, $property))
            ->isEqualTo($value);
    }

    /**
     * @Given /^the response has property "([^"]*)" with size (\d+)$/
     */
    public function theResponseHasPropertyWithSize($property, $size) {
        $data = $this->getJson();

        $this->assert
            ->phpArray($this->getProperty($data, $property))
            ->hasSize($size);
    }

    private function getProperty($data, $property) {
        if (strrpos($property, '/')!==false) {
            $parts = explode('/', $property);
            $data;
            $n = 0;
            while ($n<count($parts)) {
                if (is_null($data[$parts[$n]]) && ($n+1)==count($parts)) {
                    $data = '';
                } else {
                    if (!isset($data[$parts[$n]])) {
                        throw new Exception("Complex property '".$property."' is not set!\n");
                    }
                    $data = $data[$parts[$n]];
                }
                $n++;
            }
            return $data;
        } elseif (isset($data[$property])) {
            return $data[$property];
        } else {
            throw new Exception("Property '".$property."' is not set!\n");
        }
    }

    private function getJson() {
        if (!$this->json_decoded) {
            $this->json_data = json_decode($this->response->getBody(true), true);

            if (!$this->json_data) {
                throw new Exception("Response was not JSON\n" . $this->response->getBody(true));
            }
            $this->json_decoded = true;
            return $this->json_data;
        } else {
            return $this->json_data;
        }
    }

    private function getSessionId() {
        foreach ($this->jar->toArray() as $cookie) {
            if ($cookie['Name']=='phyxo_id') { // @TODO: retrieve name from conf
                return $cookie['Value'];
            }
        }

        return null;
    }

    /**
     * @Then /^print JSON$/
     */
    public function printJson() {
        print_r($this->getJson());
    }
}
