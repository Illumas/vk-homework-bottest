<?php
namespace HwBot;

/* Настройки базы данных */
const DB_NAME = 'id8991113_hmbot9a'; // Название базы данных
const DB_USER = 'id8991113_illumas'; // Имя пользователя базы данных
const DB_PW = '010102Gg'; // Пароль от базы даных
const DB_HW_TABLE = 'homework_table'; // Название таблицы для домашнего задания

/* Настройки API ВКонтакте */
// Используемая версия API (Управление -> Настройки -> Работа с API -> Callback API -> Версия API)
const VK_API_VERSION = '5.92';
// Код подтверждения Callback API (Управление -> Настройки -> Работа с API -> Callback API ->
// Строка, которую должен вернуть сервер)
const CALLBACK_API_CONFIRMATION_TOKEN = 'd95247da';
// Код доступа сообщества (Управление -> Настройки -> Работа с API -> Ключи доступа)
// N.B.: ключ должен иметь доступ к сообщениям сообщества
const VK_API_ACCESS_TOKEN = '84a947aa997d14e3dc0f0df660105ecd3ed4d7599a8855d89ee87f911142d1b4c7b8e30a9d71b1dc31f45';
// Идентификатор сообщества
const COMMUNITY_ID = 179760745;

// Не менять:
const VK_API_ENDPOINT = 'https://api.vk.com/method/'; // Адрес API
const EVENT_CONFIRMATION = 'confirmation'; // Идентификатор события подтверждения
const EVENT_MESSAGE_NEW = 'message_new'; // Идентификатор события сообщения

/* Другое */
const DEV_MODE = true; // true для режима разработчика, иначе false
