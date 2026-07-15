<?php
/**
 * Работа с базой клиентских аккаунтов accounts.xml: вход и регистрация.
 * Соответствует PartnerService/ClientService/{ClientLogin,PartnerClientRegistration}.
 */
final class Accounts
{
    private string $file;
    private SimpleXMLElement $xml;

    public function __construct(string $file = Config::ACCOUNTS_FILE)
    {
        $this->file = $file;
        if (!file_exists($file)) {
            $this->xml = new SimpleXMLElement('<accounts/>');
            $this->save();
        }
        $this->xml = simplexml_load_file($file);
        if ($this->xml === false) {
            throw new RuntimeException('Не удалось разобрать accounts.xml');
        }
    }

    /** Проверка логина/пароля. Возвращает данные аккаунта либо null. */
    public function authenticate(string $login, string $password): ?array
    {
        foreach ($this->xml->account as $account) {
            if ((string)$account['login'] === $login && (string)$account['password'] === $password) {
                return $this->accountToArray($account);
            }
        }
        return null;
    }

    public function findByLogin(string $login): ?array
    {
        foreach ($this->xml->account as $account) {
            if ((string)$account['login'] === $login) {
                return $this->accountToArray($account);
            }
        }
        return null;
    }

    public function findByGuid(string $guid): ?array
    {
        if ($guid === '') {
            return null;
        }
        foreach ($this->xml->account as $account) {
            if ((string)$account['guid'] === $guid) {
                return $this->accountToArray($account);
            }
        }
        return null;
    }

    /** Привязывает device guid (partnerClientId) к аккаунту после успешного логина. */
    public function bindGuid(string $login, string $guid): void
    {
        foreach ($this->xml->account as $account) {
            if ((string)$account['login'] === $login) {
                $account['guid'] = $guid;
                $this->save();
                return;
            }
        }
    }

    /** Регистрация нового клиента. Возвращает false, если email уже занят. */
    public function register(
        string $email,
        string $password,
        string $firstName,
        string $lastName,
        string $middleName,
        string $guid
    ): bool {
        if ($this->findByLogin($email) !== null) {
            return false;
        }
        $account = $this->xml->addChild('account');
        $account->addAttribute('login', $email);
        $account->addAttribute('password', $password);
        $account->addAttribute('firstName', $firstName);
        $account->addAttribute('lastName', $lastName);
        $account->addAttribute('middleName', $middleName);
        $account->addAttribute('email', $email);
        $account->addAttribute('clientAccount', '0');
        $account->addAttribute('guid', $guid);
        $this->save();
        return true;
    }

    private function accountToArray(SimpleXMLElement $account): array
    {
        return [
            'Login'          => (string)$account['login'],
            'FirstName'      => (string)$account['firstName'],
            'LastName'       => (string)$account['lastName'],
            'MiddleName'     => (string)$account['middleName'],
            'Email'          => (string)$account['email'],
            'ClientAccount'  => (string)$account['clientAccount'],
            'Guid'           => (string)$account['guid'],
        ];
    }

    private function save(): void
    {
        $dom = dom_import_simplexml($this->xml)->ownerDocument;
        $dom->formatOutput = true;
        $dom->save($this->file);
    }
}
