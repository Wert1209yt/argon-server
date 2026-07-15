<?php
/**
 * PartnerService/ClientService/*
 * ClientLogin, PartnerClientRegistration
 */
final class ClientService
{
    private Accounts $accounts;

    public function __construct()
    {
        $this->accounts = new Accounts();
    }

    /** GET ClientLogin/?...&partnerClientId=&clientLogin=&clientPassword= */
    public function clientLogin(array $p): array
    {
        $guid = $p['partnerClientId'] ?? '';
        $login = $p['clientLogin'] ?? '';
        $password = $p['clientPassword'] ?? '';

        if ($login === '' || $password === '') {
            return Response::error('Client_Login_BadRequest');
        }

        $account = $this->accounts->authenticate($login, $password);
        if ($account === null) {
            // SimpleOzonResult { Status: 1, Error }
            return Response::error('Client_Login_AuthFailed');
        }

        $this->accounts->bindGuid($login, $guid);

        // SimpleOzonResult: Status=2 означает успешную авторизацию (guid уже известен клиенту)
        return Response::ok();
    }

    /** POST PartnerClientRegistration/ */
    public function partnerClientRegistration(array $p): array
    {
        $guid = $p['partnerClientId'] ?? '';
        $email = $p['email'] ?? '';
        $password = $p['clientPassword'] ?? '';
        $firstName = $p['firstName'] ?? '';
        $lastName = $p['lastName'] ?? '';
        $middleName = $p['middleName'] ?? '';

        if ($email === '' || $password === '') {
            return Response::error('Client_Registration_BadRequest');
        }

        $created = $this->accounts->register($email, $password, $firstName, $lastName, $middleName, $guid);
        if (!$created) {
            return Response::error('Client_Registration_EmailAlreadyExists');
        }

        return Response::ok();
    }
}
