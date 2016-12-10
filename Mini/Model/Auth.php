<?php
use Auth0\SDK\API\Authentication;

namespace \Mini\Model;

class Auth {
    private $auth0;
    private $auth0Client;

    private $clientId;
    private $redirectUrl;
    private $domain;

    private $db;

    function __construct(string $clientId, string $clientSecret, string $cbk, string $domain, PingablePDO $db)
    {
        $this->auth0 = new Authentication($domain, $clientId);

        $this->auth0Client = $this->auth0->get_oauth_client($clientSecret, $cbk);

        $this->clientId = $clientId;
        $this->redirectUrl = $cbk;
        $this->domain = $domain;
        $this->db = $db;
    }

    private function isAuthorizedUser(string $email): bool
    {
        $q = $this->db->prepare("SELECT email FROM authorized_users WHERE email LIKE ?");
        $q->execute($email);

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
        return array(
            'clientId' => $this->clientId,
            'redirectUrl' => $this->redirectUrl,
            'domain' => $this->domain
        );
    }

    public function isLoggedIn(): bool
    {
        $userInfo = $auth0Client->getUser();

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

    public function getUsername(): string
    {
        return $this->auth0Client->getUser()['nickname'];
    }

    public function logout($to = NULL): string
    {
        return $this->auth0Client->logout();
    }
}
