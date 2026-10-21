<?php

namespace Database\Seeders;

use App\Models\KnowledgeDocument;
use App\Models\SupportTicket;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo-store'],
            [
                'name' => 'TechGear & CourseHub Store',
                'ai_provider' => 'openrouter',
                'ai_model' => 'openai/gpt-4o-mini',
                'confidence_threshold' => 0.65,
                'system_prompt' => 'Вы — вежливый AI-ассистент поддержки первой линии магазина TechGear & CourseHub. Отвечайте четко по базе знаний и указывайте сроки.',
                'plan' => 'growth',
                'subscription_status' => 'active',
                'monthly_quota' => 3000,
                'used_quota' => 142,
                'cost_per_human_ticket' => 4.50,
            ]
        );

        Tenant::firstOrCreate(
            ['slug' => 'cloudsaas-pro'],
            [
                'name' => 'CloudSaaS Pro Academy',
                'ai_provider' => 'claude',
                'ai_model' => 'claude-3-5-sonnet-latest',
                'confidence_threshold' => 0.70,
                'system_prompt' => 'You are the AI support concierge for CloudSaaS Pro Academy. Help students with course access, certificates, and billing.',
                'plan' => 'scale',
                'subscription_status' => 'active',
                'monthly_quota' => 15000,
                'used_quota' => 890,
                'cost_per_human_ticket' => 6.00,
            ]
        );

        if ($tenant->documents()->count() === 0) {
            $docs = [
                [
                    'title' => 'Правила возврата и обмена товаров (Return Policy)',
                    'category' => 'return_policy',
                    'content' => "Возврат товара надлежащего качества возможен в течение 14 календарных дней с момента получения заказа при сохранении товарного вида, заводских пломб и чека.\n\nДеньги возвращаются на банковскую карту покупателя в течение 3 рабочих дней после поступления товара на наш склад. Для оформления возврата напишите номер заказа или вызовите оператора.",
                    'chunks_count' => 2,
                ],
                [
                    'title' => 'Доставка и отслеживание заказов (Shipping & Tracking FAQ)',
                    'category' => 'shipping',
                    'content' => "Все заказы отправляются курьерскими службами СДЭК и Boxberry в день оформления (если заказ оплачен до 16:00 МСК).\n\nТрек-номер для отслеживания автоматически приходит в SMS и Telegram через 2 часа после передачи посылки курьеру. Бесплатная доставка действует при заказе от 5 000 ₽.",
                    'chunks_count' => 2,
                ],
                [
                    'title' => 'Совместимость аксессуаров и гарантия (Catalog & Compatibility)',
                    'category' => 'catalog',
                    'content' => "Магнитные док-станции MagCharge Pro полностью совместимы с iPhone 13/14/15/16 и наушниками AirPods Pro 2 (разъем USB-C). На все устройства предоставляется официальная гарантия 12 месяцев.",
                    'chunks_count' => 1,
                ],
            ];

            foreach ($docs as $doc) {
                KnowledgeDocument::create(array_merge($doc, [
                    'tenant_id' => $tenant->id,
                    'status' => 'indexed',
                    'last_indexed_at' => now(),
                ]));
            }
        }

        if ($tenant->tickets()->count() === 0) {
            $t1 = SupportTicket::create([
                'tenant_id' => $tenant->id,
                'telegram_chat_id' => '704519201',
                'customer_name' => 'Алексей Смирнов',
                'customer_username' => 'alex_smirnov',
                'status' => 'resolved',
                'was_deflected' => true,
                'intent' => 'return_refund',
                'avg_confidence' => 0.92,
                'last_message_at' => now()->subMinutes(18),
            ]);

            $t1->messages()->createMany([
                [
                    'sender_type' => 'customer',
                    'sender_name' => 'Алексей Смирнов',
                    'content' => 'Подскажите, в течение скольких дней я могу вернуть нераспакованные наушники и когда вернутся деньги?',
                ],
                [
                    'sender_type' => 'ai',
                    'sender_name' => 'PulseDesk AI (openrouter)',
                    'content' => 'По данным базы знаний («Правила возврата и обмена товаров»): Вы можете оформить возврат в течение 14 календарных дней с момента получения. Деньги возвращаются на карту в течение 3 рабочих дней.',
                    'confidence_score' => 0.92,
                    'provider_used' => 'openrouter',
                    'sources' => [['title' => 'Правила возврата и обмена товаров (Return Policy)', 'score' => 0.92]],
                ],
            ]);

            $t2 = SupportTicket::create([
                'tenant_id' => $tenant->id,
                'telegram_chat_id' => '704519202',
                'customer_name' => 'Екатерина Волкова',
                'customer_username' => 'kate_v',
                'status' => 'resolved',
                'was_deflected' => true,
                'intent' => 'product_compatibility',
                'avg_confidence' => 0.89,
                'last_message_at' => now()->subDays(1),
            ]);

            $t2->messages()->createMany([
                [
                    'sender_type' => 'customer',
                    'sender_name' => 'Екатерина Волкова',
                    'content' => 'Подойдет ли док-станция MagCharge Pro к iPhone 15 и какая гарантия?',
                ],
                [
                    'sender_type' => 'ai',
                    'sender_name' => 'PulseDesk AI (openrouter)',
                    'content' => 'Да, док-станции MagCharge Pro полностью совместимы с iPhone 13/14/15/16 и AirPods Pro 2. Официальная гарантия составляет 12 месяцев.',
                    'confidence_score' => 0.89,
                    'provider_used' => 'openrouter',
                    'sources' => [['title' => 'Совместимость аксессуаров и гарантия', 'score' => 0.89]],
                ],
            ]);

            $t3 = SupportTicket::create([
                'tenant_id' => $tenant->id,
                'telegram_chat_id' => '704519203',
                'customer_name' => 'Дмитрий Орлов',
                'customer_username' => 'dmitry_orlov',
                'status' => 'escalated',
                'was_deflected' => false,
                'intent' => 'human_request',
                'escalation_reason' => 'Customer requested custom B2B invoice & live manager',
                'avg_confidence' => 0.31,
                'last_message_at' => now()->subMinutes(4),
            ]);

            $t3->messages()->createMany([
                [
                    'sender_type' => 'customer',
                    'sender_name' => 'Дмитрий Орлов',
                    'content' => 'Здравствуйте! Хотим закупить 25 комплектов на юрлицо по безналу со скидкой, позовите живого менеджера!',
                ],
                [
                    'sender_type' => 'ai',
                    'sender_name' => 'PulseDesk AI (openrouter)',
                    'content' => 'Перевожу ваш диалог на живого специалиста отдела продаж и поддержки — менеджер ответит вам в этом чате в ближайшее время.',
                    'confidence_score' => 0.31,
                    'provider_used' => 'openrouter',
                    'sources' => [],
                ],
            ]);
        }
    }
}
