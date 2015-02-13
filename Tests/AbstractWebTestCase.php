<?php
/**
 * Created by PhpStorm.
 * User: antoine.darche
 * Date: 30/01/2015
 * Time: 11:51
 */

namespace LITC\TestBundle\Tests;

use LITC\Model\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Class AbstractWebTestCase
 *
 * @package LITC\TestBundle\Tests
 * @author darche.antoine@gmail.com
 */
abstract class AbstractWebTestCase extends WebTestCase
{
    const CONTENT_TYPE_HTML = 'text/html; charset=UTF-8';
    const CONTENT_TYPE_XML  = 'text/xml; charset=UTF-8';
    const CONTENT_TYPE_JSON = 'application/json; charset=UTF-8';

    const STATUS_CODE_SUCCESS   = 200;
    const STATUS_CODE_REDIRECT  = 302;
    const STATUS_CODE_NO_RIGHTS = 405;

    /* @var Client */
    protected $client = '';

    /* @var Session */
    protected $session = '';

    /**
     * Create a default client
     */
    protected function createClientWithoutSession()
    {
        $this->client = static::createClient();
    }

    /**
     * Create a default client with a session start
     *
     * @var boolean $logged force client to login before route
     */
    protected function createClientWithOpenSession()
    {
        self::createClientWithoutSession();

        $sessionMock = new Session(new MockFileSessionStorage());

        $container = $this->client->getContainer();
        $container->set('session', $sessionMock);
    }

    /**
     * Create a client authenticated
     *
     * @param $username
     */
    protected function createAuthorizedClient($username)
    {
        self::createClientWithoutSession();

        $container  = $this->client->getContainer();
        $session    = $container->get('session');

        $userManager    = $container->get('fos_user.user_manager');
        $loginManager   = $container->get('fos_user.security.login_manager');

        $firewallName = $container->getParameter('fos_user.firewall_name');

        // Get user
        $user = $userManager->findUserBy(array('username' => $username));

        // Get user authenticated
        $loginManager->loginUser($firewallName, $user);

        // Set user in session
        $container->get('session')->set('_security_' . $firewallName,
        serialize($container->get('security.context')->getToken()));
        $container->get('session')->save();

        // Set user in cookie
        $this->client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));

    }

    /**
     * Testes the main assertions
     *
     * @param $contentType
     */
    protected function assertSuccessResponse($contentType, $statusCode=self::STATUS_CODE_SUCCESS)
    {
        $this->assertTrue(
            $this->client->getResponse()->headers->contains(
                'Content-Type',
                $contentType
            )
        );

        $this->assertCodeResponse($statusCode);
    }

    /**
     * Multiple statements from the expected status code
     *
     * @param integer $statusCode
     */
    protected function assertCodeResponse($statusCode)
    {
        $clientResponse = $this->client->getResponse();
        $this->assertEquals($statusCode, $clientResponse->getStatusCode());

        // Is response invalid?
        if($statusCode < 100 || $statusCode >= 600) {
            $this->isInvalid($clientResponse->isInvalid());
        }

        // Is response informative?
        if($statusCode >= 100 && $statusCode < 200) {
            $this->assertTrue($clientResponse->isInformational());
        }

        // Is response successful?
        elseif($statusCode >= 200 && $statusCode < 300) {
            $this->assertTrue($clientResponse->isSuccessful());

            // Is the response OK?
            if($statusCode == 200) {
                $this->assertTrue($clientResponse->isOk());
            }

            // Is the response empty?
            elseif(in_array($statusCode, array(204, 304))) {
                $this->assertTrue($clientResponse->isEmpty());
            }

            elseif(in_array($statusCode, array(201, 301, 302, 303, 307, 308))) {
                $this->assertTrue($clientResponse->isRedirect());
            }
        }

        // Is the response a redirect?
        elseif($statusCode >= 300 && $statusCode < 400) {
            $this->assertTrue($clientResponse->isRedirection());
        }

        // Is there a client error?
        elseif($statusCode >= 400 && $statusCode < 500) {
            $this->assertTrue($clientResponse->isClientError());

            // Is the response forbidden?
            if($statusCode == 403) {
                $this->assertTrue($clientResponse->isForbidden());
            }

            // Is the response a not found error?
            elseif($statusCode == 404) {
                $this->assertTrue($clientResponse->isNotFound());
            }
        }

        // Was there a server side error?
        elseif($statusCode >= 500 && $statusCode < 600) {
            $this->assertTrue($clientResponse->isServerError());
        }
    }

    /**
     * Testes the main assertions html
     */
    protected function assertHtmlResponse($statusCode=self::STATUS_CODE_SUCCESS)
    {
        $this->assertSuccessResponse(self::CONTENT_TYPE_HTML, $statusCode);
    }

    /**
     * Testes the main assertions xml
     */
    protected function assertXmlResponse($statusCode=self::STATUS_CODE_SUCCESS)
    {
        $this->assertSuccessResponse(self::CONTENT_TYPE_XML, $statusCode);
    }

    /**
     * Testes the main assertions json
     */
    protected function assertJsonResponse($statusCode=self::STATUS_CODE_SUCCESS)
    {
        $this->assertSuccessResponse(self::CONTENT_TYPE_JSON, $statusCode);
    }

    /**
     * Get route from configuration
     *
     * @var string $routeName   Route name
     * @var string $params      Route params
     *
     * @return string
     */
    protected function getRoute($routeName, $params=array())
    {
        return $this->client->getContainer()
            ->get('router')
            ->generate(
                $routeName,
                $params,
                false
            );
    }

    /**
     * Debug unit test
     *
     * @param bool $withContent Force to show content response
     */
    protected function debug($withContent=false)
    {
        if($withContent) {
            var_dump(
                $this->client->getResponse()->getContent()
            );
        }

        var_dump(
            $this->client->getResponse()->headers->get('Content-Type'),
            $this->client->getResponse()->isSuccessful(),
            $this->client->getResponse()->getStatusCode()
        );
        die;
    }
	
	/**
	* TODO à documenter
	*/
	protected function assertJsonResponse($statusCode=self::STATUS_CODE_SUCCESS)
    {
        $this->assertSuccessResponse('application/json', $statusCode);

        // Test si le json est valide
        $content = $this->client->getResponse()->getContent();
        $decode = json_decode($content);
        $this->assertTrue(
            ($decode != null && $decode != false),
            'json valide : [' . $content . ']'
        );
    }
    
	/**
	* TODO à documenter
	*/
    protected function assertXmlResponse($statusCode=self::STATUS_CODE_SUCCESS)
	{
        parent::assertXmlResponse($statusCode);
        
        // Test si le xml est valide
        $xml = new \XMLReader();
        $this->assertTrue(
            $xml->xml(
                $this->client->getResponse()->getContent(),
                null,
                LIBXML_DTDVALID
        ));
    }
}