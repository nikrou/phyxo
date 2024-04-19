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

namespace App\Tests\Behat;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Exception;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

class ApiContext implements Context
{
    private readonly Client $client;

    private readonly Request $request;

    private ResponseInterface $response;

    private $json_data;

    private $json_decoded = false;

    public function __construct(private string $phyxoVersion, string $apiBaseUrl, private readonly ContainerInterface $driverContainer)
    {
        $this->client = new Client(['base_uri' => $apiBaseUrl, 'cookies' => new CookieJar(), 'exceptions' => true]);
        $this->request = new Request('GET', $apiBaseUrl);
    }

    protected function getContainer():  ContainerInterface
    {
        return $this->driverContainer;
    }

    /**
     * @Given I am authenticated for api as :username with password :password
     */
    public function iAmAuthenticatedForApiAs(string $username, string $password)
    {
        $table = new TableNode([['username', 'password'], [$username, $password]]);

        $this->iSendARequestWithValues('POST', 'pwg.session.login', $table);
    }

    /**
     * Sends HTTP request to specific URL with field values from Table.
     *
     * @param string    $http_method    request method
     * @param string    $method         relative url
     * @param TableNode $values         table of post values
     *
     * @When /^I send a "(GET|POST)" request to "([^"]*)" with values:$/
     * @When /^I send a "(GET|POST)" request to "([^"]*)"$/
     */
    public function iSendARequestWithValues(string $http_method, string $method, ?TableNode $values = null)
    {
        $requestOptions = [];
        if ($http_method == 'GET') {
            $requestOptions['query']['method'] = $method;
        } elseif ($http_method == 'POST') {
            $requestOptions['form_params'] = [];
            if ($values !== null) {
                foreach ($values->getColumnsHash() as $columHash) {
                    $requestOptions['form_params'] = $columHash;
                }
            }
            $requestOptions['form_params']['method'] = $method;
        }

        try {
            $this->json_decoded = false;
            $this->response = $this->client->send($this->request->withMethod($http_method), $requestOptions);
        } catch (Exception $e) {
            $this->json_data = ['message' => $e->getMessage()];
            $this->json_decoded = true;
        }
    }

    /**
     * Checks that response has specific status code.
     *
     * @param int $code status code
     *
     * @Then the response status code should be :code
     */
    public function theResponseCodeShouldBe(int $code)
    {
        if ($code !== $this->response->getStatusCode()) {
            throw new Exception(sprintf('Response code was %d but should be %d', $this->response->getStatusCode(), $code));
        }
    }

    /**
     * @Then the response body contains JSON:
     */
    public function theResponseBodyContainsJson(PyStringNode $json)
    {
        if (trim($json) !== trim($this->getJsonAsString())) {
            throw new Exception(sprintf('Response was "%s" but should be "%s"', $this->getJsonAsString(), $json));
        }
    }

    /**
     * @Given the response is JSON
     */
    public function theResponseIsJson()
    {
        $this->getJson();
    }

    /**
     * @Given the response has property :property
     */
    public function theResponseHasProperty(string $property)
    {
        $data = $this->getJson();

        return $this->getProperty($data, $property);
    }

    /**
     * @Given the response has property :property equals to :value
     * @Given the response has property :property equals to :value of type :type
     */
    public function theResponseHasPropertyEqualsTo(string $property, string $value, string $type = '')
    {
        $data = $this->getJson();

        $getValue = $this->getProperty($data, $property);

        if ($value === 'PHYXO_VERSION') {
            $value = $this->phyxoVersion;
        }

        if ($value != $getValue) {
            throw new Exception(sprintf('Property "%s" value was "%s" but should be "%s"', $property, $getValue, $value));
        }
    }

    private function getProperty($data, $property)
    {
        if (strrpos((string) $property, '/') !== false) {
            $parts = explode('/', (string) $property);
            $n = 0;
            while ($n < count($parts)) {
                if (is_null($data[$parts[$n]]) && ($n + 1) == count($parts)) {
                    $data = '';
                } else {
                    if (!isset($data[$parts[$n]])) {
                        throw new Exception("Complex property '" . $property . "' is not set!\n");
                    }
                    $data = $data[$parts[$n]];
                }
                $n++;
            }
            return $data;
        } elseif (isset($data[$property])) {
            return $data[$property];
        } else {
            throw new Exception("Property '" . $property . "' is not set!\n");
        }
    }

    private function getJson()
    {
        if (!$this->json_decoded) {
            $this->json_data = json_decode($this->response->getBody(), true);

            if (!$this->json_data) {
                throw new Exception("Response was not JSON\n" . $this->response->getBody());
            }
            $this->json_decoded = true;
            return $this->json_data;
        } else {
            return $this->json_data;
        }
    }

    private function getJsonAsString(): string
    {
        return json_encode($this->getJson());
    }

    /**
     * @Then print JSON
     */
    public function printJson()
    {
        print_r($this->getJson());
    }
}
