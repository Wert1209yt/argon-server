<?php
/**
 * ARGON.ru — конфигурация сервера.
 * Реверс-инжиниринг протокола Ozon.ru Android app (2012), PartnerService REST API.
 */

final class Config
{
    // Учётные данные партнёра (см. Constants.java: PARTNER_LOGIN / PARTNER_PASSWORD)
    public const PARTNER_LOGIN    = 'androidapp';
    public const PARTNER_PASSWORD = 'MaiNNqA859bnMqw';

    // URL сервера для мультимедии и т.д.
    public const BASE_URL         = 'http://198.168.3.6/';

    // Пути к "БД" в XML
    public const DATA_DIR      = __DIR__ . '/../data';
    public const DB_FILE       = self::DATA_DIR . '/db.xml';
    public const ACCOUNTS_FILE = self::DATA_DIR . '/accounts.xml';
    public const CARTS_DIR     = self::DATA_DIR . '/carts';

    // Базовый URL для картинок
    public const MEDIA_BASE_URL = self::BASE_URL . 'multimedia/';
}
