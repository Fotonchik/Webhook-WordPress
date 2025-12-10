# Webhook-WordPress

![PHP](https://img.shields.io/badge/PHP-8.1-purple)
[![WordPress](https://img.shields.io/badge/WordPress-%2321759B.svg?logo=wordpress&logoColor=white)](#)
![MySQL](https://img.shields.io/badge/MySQL-8.x-blue)

## Особенности
-  Принимает POST-запрос на endpoint /wp-json/leads/v1/submit  → Запрос curl -X POST был успешно обработан.
-  Валидирует данные (email, phone, name) → При попытке отправить невалидный email или без имени плагин вернул бы ошибку 400.
-  Сохраняет в custom post type "Leads" → В ответе указан "lead_id": 6. Новую запись можно найти в админке WordPress в меню Leads.
-  Отправляет вебхук на указанный URL (n8n) → Плагин выполнил попытку отправки, что видно по ошибке таймаута. Осталось заменить URL-заглушку на реальный.

Запуск 

Архитектура:
- Google Sheets (источник ключевых слов).
- n8n (оркестратор) с двумя основными workflow.
- WordPress (цель для страниц и источник для форм). ..\Local Sites\webhook\app\public\wp-content\plugins\lead-webhook-processor
- EspoCRM (цель для данных лидов).


Описание каждого компонента и его роли
- Google Sheets: Источник данных для контент-плана. Служит удобным интерфейсом для SEO-специалиста. Содержит список ключевых слов, мета-теги и структуру будущих страниц.
- n8n (Workflow 1): Оркестратор создания контента. Автоматически забирает данные из Google Sheets, обрабатывает и создает SEO-страницы в WordPress через REST API.
- WordPress: Целевая CMS. Принимает и публикует контент (страницы, записи). Через плагин также выступает как источник данных, отправляя лиды с форм.
- Lead Webhook Processor: Мост для данных лидов. Принимает заявки с форм, валидирует, сохраняет локально и мгновенно отправляет в n8n для дальнейшей обработки.
- n8n (Workflow 2): Центр обработки лидов. Принимает вебхук от плагина, дополнительно валидирует и обогащает данные, затем создает сделки или контакты в CRM.
- EspoCRM: Финал бизнес-процесса. Получает подготовленные данные о лидах для работы отдела продаж.

Псевдокод ключевых операций (валидация в n8n)
- Для Workflow 2 (обработка лида из вебхука) в Function Node можно добавить проверку:
```javascript
// Псевдокод валидации
const lead = $input.all()[0].json;
// валидация
if (!lead.email || !lead.email.includes('@')) {
  throw new NodeOperationError(this.getNode(), 'Invalid email format', { itemIndex: 0 })[citation:8];
}
// откуда
lead.source = 'wordpress_seo_page';
lead.received_at = new Date().toISOString();
// → API EspoCRM
const payloadForEspo = {
  'name': `${lead.name}`,
  'emailAddress': lead.email,
  'phoneNumber': lead.phone,
  'description': `Источник: ${lead.source}. ID лида в WP: ${lead.lead_id}`
};
return payloadForEspo;

```
Приблизительный список n8n nodes
- Для автоматизации создания SEO-страниц из Google Sheets в WordPress в n8n понадобятся ключевые ноды: Schedule Trigger для запуска процесса по расписанию, Google Sheets Node для чтения списка ключевых слов, Function Node для валидации и преобразования данных, опционально AI Agent Node для генерации контента, и, наконец, WordPress Node (или стандартный HTTP Request Node) для публикации готовых страниц через REST API WordPress.
- Для обработки лидов с форм на WordPress в EspoCRM потребуется другой набор нод: Webhook Node будет слушать и принимать данные, отправленные вашим плагином, Function Node выполнит дополнительную проверку и обогащение данных лида, а HTTP Request Node отправит подготовленную информацию в API EspoCRM для создания контакта или сделки. Для надёжности в оба workflow стоит добавить Error Trigger и ноды для уведомлений (например, Email или Slack), чтобы оперативно получать сообщения о сбоях.
 
Описание потенциальных проблем
- 1* Дублирование лидов. Чтобы её решить, в n8n перед отправкой данных в EspoCRM нужно: искать существующий контакт по email или телефону через API CRM и обновлять его, а не создавать новый.
- 2* Потеря данных при временном сбое сети или неработоспособности n8n, когда вебхук из WordPress не доставляется. Решение: в коде плагина нужно сохранять статус отправки и реализовать фоновую задачу или админ-страницу для ручной повторной отправки неудавшихся запросов.
- 3* Падение производительности, если WordPress или n8n не справятся с большим одновременным потоком страниц или лидов. Здесь поможет внедрение очереди задач (Redis и т.п.), которая будет регулировать нагрузку, обрабатывая задачи асинхронно, а не в режиме реального времени.
- 4* Есть риск поломки workflow из-за изменений во внешних системах, например, при добавлении новой колонки в Google Sheets или обновлении API EspoCRM. Защититься от этого можно с помощью строгой валидации структуры данных на входе в n8n (Schema Validation Node) и настройки автоматических оповещений о любых ошибках выполнения workflow.

##  Автор
- GitHub: [@yourusername](https://github.com/Fotonchik)
- Portfolio: [yourportfolio.com](https://hh.ru/resume/4bde0dbeff0b000ce70039ed1f696b666c5642)

 # Результат
<img width="1096" height="102" alt="image" src="https://github.com/user-attachments/assets/e22a2aa0-1972-46a2-9d6a-3a8552baf040" />
<img width="1060" height="90" alt="image" src="https://github.com/user-attachments/assets/d749884b-74da-4fa4-b57b-a12a49daa1b8" />
