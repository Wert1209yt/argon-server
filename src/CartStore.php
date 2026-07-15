<?php
/**
 * Хранилище корзины: один XML-файл на каждый partnerClientId (guid устройства),
 * в data/carts/{guid}.xml. Соответствует PartnerService/CartService/*.
 */
final class CartStore
{
    private string $guid;
    private string $file;
    private SimpleXMLElement $xml;

    public function __construct(string $guid)
    {
        $this->guid = $guid !== '' ? $guid : '_anonymous';
        if (!is_dir(Config::CARTS_DIR)) {
            mkdir(Config::CARTS_DIR, 0777, true);
        }
        $this->file = Config::CARTS_DIR . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->guid) . '.xml';

        if (!file_exists($this->file)) {
            $this->xml = new SimpleXMLElement('<cart/>');
            $this->save();
        }
        $this->xml = simplexml_load_file($this->file);
    }

    /** items: [itemId => quantity] */
    public function getItems(): array
    {
        $result = [];
        foreach ($this->xml->item as $item) {
            $result[(int)$item['id']] = (int)$item['qty'];
        }
        return $result;
    }

    public function addOrIncrement(int $itemId, int $qty): void
    {
        $node = $this->findNode($itemId);
        if ($node !== null) {
            $node['qty'] = (int)$node['qty'] + $qty;
        } else {
            $node = $this->xml->addChild('item');
            $node->addAttribute('id', (string)$itemId);
            $node->addAttribute('qty', (string)$qty);
        }
        $this->save();
    }

    public function setQuantity(int $itemId, int $qty): void
    {
        $node = $this->findNode($itemId);
        if ($node !== null) {
            $node['qty'] = (string)$qty;
        } else {
            $node = $this->xml->addChild('item');
            $node->addAttribute('id', (string)$itemId);
            $node->addAttribute('qty', (string)$qty);
        }
        $this->save();
    }

    public function remove(int $itemId): void
    {
        $i = 0;
        foreach ($this->xml->item as $item) {
            if ((int)$item['id'] === $itemId) {
                unset($this->xml->item[$i]);
                $this->save();
                return;
            }
            $i++;
        }
    }

    public function clear(): void
    {
        $this->xml = new SimpleXMLElement('<cart/>');
        $this->save();
    }

    private function findNode(int $itemId): ?SimpleXMLElement
    {
        foreach ($this->xml->item as $item) {
            if ((int)$item['id'] === $itemId) {
                return $item;
            }
        }
        return null;
    }

    private function save(): void
    {
        $dom = dom_import_simplexml($this->xml)->ownerDocument;
        $dom->formatOutput = true;
        $dom->save($this->file);
    }
}
