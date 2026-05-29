# Ballkorg Cookie Consent

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue?logo=wordpress)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4?logo=php)](https://php.net)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![Version](https://img.shields.io/badge/version-5.1.0-green)](CHANGELOG.md)

WordPress-плагин для управления cookie-согласием в соответствии с GDPR и Федеральным законом РФ №152-ФЗ «О персональных данных».

Плагин логирует пользовательские согласия в отдельную таблицу базы данных, поддерживает отложенную загрузку Яндекс.Метрики и Google Consent Mode v2, а также не хранит IP-адреса в открытом виде.

## Возможности

- **Cookie banner** — адаптивный баннер согласия с кнопками принятия и отклонения
- **Логирование согласий** — каждое действие пользователя сохраняется в отдельную таблицу БД
- **Privacy-first хранение** — IP-адреса не сохраняются в открытом виде; используется SHA-256 hash и анонимизированный префикс
- **Отложенная загрузка аналитики** — Яндекс.Метрика и Google Consent Mode v2 загружаются только после согласия пользователя
- **Экспорт CSV** — выгрузка логов согласий из панели администратора
- **Выбор языка** — переключение языка плагина и баннера из настроек
- **Управление баннером** — включение и отключение frontend-баннера
- **Контроль удаления данных** — настройка очистки логов при удалении плагина
- **Стандарты WordPress** — namespaced OOP-архитектура и подготовленные SQL-запросы

## Требования

| Зависимость | Минимальная версия |
|---|---|
| WordPress | 6.0 |
| PHP | 8.0 |
| MySQL | 5.7 / MariaDB 10.3 |

## Установка

### Ручная установка

1. Скачайте последнюю версию из GitHub Releases
2. Загрузите папку `ballkorg-cookie-consent` в `/wp-content/plugins/`
3. Активируйте плагин через меню WordPress

### Установка через WP-CLI

```bash
wp plugin install https://github.com/ballkorg/ballkorg-cookie-consent/archive/refs/heads/main.zip --activate
