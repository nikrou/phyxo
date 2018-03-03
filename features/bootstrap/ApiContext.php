<?php
/*
 * This file is part of Phyxo package
 *
 * Copyright(c) Nicolas Roudaire  https://www.phyxo.net/
 * Licensed under the GPL version 2.0 license.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

use mageekguy\atoum\asserter as Atoum;

class ApiContext implements Context
{
    private $assert;
    private $parameters = array();
    private $baseUrl;
    private $client;
    private $response;

    // json stuff
    private $json_data = null;
    private $json_decoded = false;
    private $jar = null;

    public function __construct(array $parameters) {
        $this->assert = new Atoum\generator();

        $this->parameters = $parameters;
        $this->baseUrl = $parameters['api_base_url'];
        $this->jar = new CookieJar();

        $this->client = new Client(array('cookies' => $this->jar, 'http_errors' => false));
    }

    /**
     * @Given /^I am authenticated for api as "([^"]*)" with password "([^"]*)"$/
     */
    public function iAmAuthenticatedForApiAs($username, $password) {
        $params = array();
        $params['method'] = 'pwg.session.login';
        $params['format'] = 'json';
        $params['username'] = $username;
        $params['password'] = $password;

        $this->response = $this->client->request('POST', $this->baseUrl, ['form_params' => $params]);
        $this->json_decoded = false;
    }

    /**
     * Sends HTTP request to specific relative URL.
     *
     * @param string $method request method
     *
     * @When /^(?:I )?send a ([A-Z]+) request to "([^"]+)"$/
     */
    public function iSendARequest($http_method, $method) {
        $params = array();
        if ($http_method=='GET') {
            $params['query'] = array('method' => $method, 'format' => 'json');
        } elseif ($http_method=='POST') {
            $params['form_data'] = array('method', $method, 'format' => 'json');
        }

        $this->json_decoded = false;
        $this->response = $this->client->request($http_method, $this->baseUrl, $params);
    }

    /**
     * Sends HTTP request to specific URL with field values from Table.
     *
     * @param string    $method request method
     * @param TableNode $values   table of post values
     *
     * @When /^(?:I )?send a ([A-Z]+) request to "([^"]+)" with values:$/
     */
    public function iSendARequestWithValues($http_method, $method, TableNode $values) {
        $fields_file = array();
        $request_params = array();
        $params = array();
        $params['method'] = $method;
        $params['format'] = 'json';

        if ($http_method=='GET') {
            foreach ($values->getRowsHash() as $key => $val) {
                if (preg_match('`^SAVED:(.*)$`', $val, $matches)) {
                    $params[$key] = $this->getSaved($matches[1]);
                } elseif ($key=='pwg_token') {
                    $params['pwg_token'] = $this->get_pwg_token($this->getSessionId());
                } else {
                    $params[$key] = $val;
                }
            }
            $request_params['query'] = $params;
        } elseif ($http_method=='POST') {
            foreach ($values->getRowsHash() as $key => $val) {
                if (preg_match('`^SAVED:(.*)$`', $val, $matches)) {
                    $value = $this->getSaved($matches[1]);

                    if ($key=='tags') { // @TODO: find a better way to add ~~ around tags id
                        $value = '~~'.$value.'~~';
                    }
                    $params[$key] = $value;
                } elseif ($key=='pwg_token') {
                    $params['pwg_token'] = $this->get_pwg_token($this->getSessionId());
                } elseif (preg_match('`FILE:(.*)$`', $key, $matches)) {
                    $fields_file[] = array(
                        'name' => $matches[1],
                        'contents' => fopen($val, 'r')
                    );
                } else {
                    if (preg_match('`\[(.*)]`', $val, $matches)) {
                        $val = array_map('trim', explode(',',  $matches[1]));
                        foreach ($val as &$v) {
                            if (preg_match('`^SAVED:(.*)$`', $v, $matches)) {
                                $v = $this->getSaved($matches[1]);
                                if ($key=='tags') { // @TODO: find a better way to add ~~ around tags id
                                    $v = '~~'.$v.'~~';
                                }
                            }
                        }
                    }

                    $params[$key] = $val;
                }
            }
            if (!empty($fields_file)) {
                $request_params['multipart'] = $fields_file;
                foreach ($params as $key => $value) {
                    $request_params['multipart'][] = array(
                        'name' => $key,
                        'contents' => $value
                    );
                }
            } else {
                $request_params['form_params'] = $params;
            }
        }

        $this->json_decoded = false;
        $this->response = $this->client->request($http_method, $this->baseUrl, $request_params);
    }

    /**
     * @Given /^the response has property "([^"]*)" equals to PHYXO_VERSION$/
     */
    public function theResponseHasPropertyEqualsToPhyxoVersion($version) {
        $conf_content = file_get_contents(__DIR__.'/../../include/constants.php');
        if (preg_match("`define\('PHPWG_VERSION', '([^\'])'\)`", $conf_content, $matches)) {
            $this->assert
                ->string($matches[1])
                ->isEqualTo($version);
        }
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
     * @Given /^the response has no property "([^"]*)"$/
     */
    public function theResponseHasNoProperty($property) {
        $data = $this->getJson();

        try {
            $this->getProperty($data, $property);
            return false;
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * @Given /^the response has property "([^"]*)" equals to array "\[([^"]*)\]"$/
     */
    public function theResponseHasPropertyEqualsToArray($property, $string_values) {
        $data = $this->getJson();
        $values = explode(',', $string_values);
        foreach ($values as &$value) {
            $value = preg_replace_callback(
                '`SAVED:([a-zA-Z0-9_-]*)`',
                function($matches) {
                    return $this->getSaved($matches[1]);
                },
                $value
            );
        }

        $this->assert
            ->array($this->getProperty($data, $property))
            ->isEqualTo($values);
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
                return $this->getSaved($matches[1]);
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
                if (($n+1)===count($parts)) {
                    if (isset($data[$parts[$n]])) {
                        return $data[$parts[$n]];
                    } else {
                        throw new \Exception("Property '".$property."' is not set!\n");
                    }
                } else {
                    if (!isset($data[$parts[$n]])) {
                        throw new \Exception("Complex property '".$property."' is not set!\n");
                    }
                    $data = $data[$parts[$n]];
                }
                $n++;
            }
            return $data;
        } elseif (isset($data[$property])) {
            return $data[$property];
        } else {
            throw new \Exception("Property '".$property."' is not set!\n");
        }
    }

    private function getJson() {
        if (!$this->json_decoded) {
            $this->json_data = json_decode($this->response->getBody(true), true);

            if (!$this->json_data) {
                throw new \Exception("Response was not JSON\n" . $this->response->getBody(true));
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
