<?php

namespace Teknasyon\Phalcon\Auth\Drivers;

use Phalcon\DiInterface;
use Phalcon\Session\AdapterInterface as SessionInterface;
use Teknasyon\Phalcon\Auth\Interfaces\AuthDriver;
use Teknasyon\Phalcon\Auth\Interfaces\User;
use Teknasyon\Phalcon\Auth\Interfaces\UserProvider;


/**
 * Class Session
 * @package Teknasyon\Phalcon\Auth\Drivers
 * @author Ilyas Serter <ilyasserter@teknasyon.com>
 */
class Session implements AuthDriver
{


    private $config;
    private $user;

    protected $userProvider;
    protected $hashingService;
    protected $sessionHandler;

    /**
     * Session constructor.
     * @param array $config
     * @param UserProvider $userProvider
     * @param DiInterface $di
     * @throws \Exception
     */
    public function __construct(array $config, UserProvider $userProvider, DiInterface $di)
    {
        $this->config = $config;
        $this->userProvider = $userProvider;

        // get session handler
        $this->sessionHandler = $di->get($config['sessionServiceName'] ?? 'session');
        // validate session handler
        if( ! $this->sessionHandler instanceof SessionInterface) {
            throw new \Exception('Session service cannot be resolved from the DI container.');
        }

        // get hashing service
        $this->hashingService = $di->get($config['hashingServiceName'] ?? 'security');

        // validate hashing service
        if( !is_object($this->hashingService)
            || !method_exists($this->hashingService,'hash')
            || !method_exists($this->hashingService,'checkHash')
        ) {
            throw new \Exception('Hashing (security) service cannot be resolved from the DI container.');
        }
    }


    /**
     * @param array $credentials
     * @return bool
     */
    public function attempt(array $credentials = []) : bool
    {
        $user = $this->userProvider->findUserByCredentials($credentials);
        if(!$user) {
            return false;
        } else if($this->hashingService->checkHash($credentials['password'],$user->getPassword())) {
            return $this->login($user);
        }

        return false;
    }

    /**
     * @return bool
     */
    public function check() : bool
    {
        return $this->sessionHandler->has('teknasyon_login') && $this->user();
    }

    /**
     * @param bool $fresh
     * @return User|null
     */
    public function user($fresh = false)
    {
        if($fresh || !$this->user) {
            $this->user = $this->userProvider->findUserById($this->sessionHandler->get('teknasyon_login'));
        }

        return $this->user;
    }

    /**
     * @TODO set user object directly instead of invoking $this->user();
     * @param User $user
     * @return bool
     */
    public function login(User $user)
    {
        $this->sessionHandler->set('teknasyon_login',$user->getId());
        return !is_null($this->user());
    }

    /**
     *
     */
    public function logout()
    {
        $this->user = null;
        $this->sessionHandler->remove('teknasyon_login');
    }


}