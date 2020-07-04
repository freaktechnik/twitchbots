<?php
namespace Mini\Model;

include_once 'csrf.php';

use Auth0\SDK\Auth0;

/* CREATE TABLE IF NOT EXISTS authorized_users (
    id int(10) unsigned NOT NULL AUTO_INCREMENT,
    email MEDIUMTEXT CHARACTER SET ascii NOT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARSET=ascii */

class Auth {
    /** @var \Auth0\SDK\Auth0 $auth0 */
    private $auth0;

    /** @var string $clientId */
    private $clientId;
    /** @var string $redirectUrl */
    private $redirectUrl;
    /** @var string $domain */
    private $domain;

    /** @var PingablePDO $db */
    private $db;

    function __construct(string $clientId, string $clientSecret, string $cbk, string $domain, PingablePDO $db)
    {
        if(!empty($clientId)) {
            $this->auth0 = new Auth0([
                'domain' => $domain,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $cbk,
            ]);
        }

        $this->clientId = $clientId;
        $this->redirectUrl = $cbk;
        $this->domain = $domain;
        $this->db = $db;
    }

    private function isAuthorizedUser(string $email): bool
    {
        $q = $this->db->prepare("SELECT email FROM authorized_users WHERE email=?");
        $q->execute(array($email));

        $result = $q->fetch();
        if(!$result) {
            return false;
        }
        else {
            return true;
        }
    }

    public function getConfig(): array
    {
        return [
            'clientId' => $this->clientId,
            'redirectUrl' => $this->redirectUrl,
            'domain' => $this->domain
        ];
    }

    public function redirectToLogin()
    {
        $this->auth0->login(generate_token('auth0'), 'github');
    }

    public function isLoggedIn(): bool
    {
        $userInfo = $this->auth0->getUser();

        if (!$userInfo) {
            return false;
        } else {
            if($this->isAuthorizedUser($userInfo['email'])) {
                return true;
            }
            else {
                return false;
            }
        }
    }

    public function getIdentifier(): string
    {
        return $this->auth0->getUser()['email'];
    }

    public function logout(): void
    {
        $this->auth0->logout();
        session_destroy();
    }
}
