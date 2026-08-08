#!/usr/bin/env node
/**
 * MyTax CLI — создание чека без запуска сервера
 * 
 * Использование (через stdin):
 *   echo '<itemsJson>' | node mytax-cli.js <orderId> <email>
 * 
 * Или через аргументы:
 *   node mytax-cli.js <orderId> <email> '<itemsJson>'
 * 
 * Пример:
 *   echo '[{"id":"1","name":"Товар","price":100,"quantity":1}]' | node mytax-cli.js 81 customer@mail.ru
 * 
 * Выводит JSON в stdout (среди возможного мусора от библиотек).
 * PHP парсит последнюю строку с {.
 */

import pkg from "lknpd-nalog-api";
import dotenv from "dotenv";
import path from "path";
import { fileURLToPath } from "url";
import { createInterface } from "readline";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

dotenv.config({ path: path.join(__dirname, '.env'), debug: false });

const { NalogApi } = pkg;

async function main() {
    const args = process.argv.slice(2);

    if (args.length < 2) {
        console.error("Использование: node mytax-cli.js <orderId> <email> [itemsJson]");
        process.exit(1);
    }

    const orderId = args[0];
    const email = args[1];

    let itemsRaw = args.slice(2).join(' ').trim();

    if (!itemsRaw) {
        itemsRaw = await readStdin();
    }

    if (!itemsRaw) {
        console.error("Не передан items JSON");
        process.exit(1);
    }

    let items;
    try {
        items = JSON.parse(itemsRaw);
    } catch (e) {
        console.error("Ошибка парсинга items JSON:", e.message);
        process.exit(1);
    }

    if (!Array.isArray(items) || items.length === 0) {
        console.error("items должен быть непустым массивом");
        process.exit(1);
    }

    const APP_NAME = process.env.APPNAME || "МЕТАЛЬКА";
    const INN = process.env.INN;
    const PASSWORD = process.env.PASSWORD;

    if (!INN || !PASSWORD) {
        console.error("INN или PASSWORD не заданы в .env");
        process.exit(1);
    }

    const nalogApi = new NalogApi({ inn: INN, password: PASSWORD });

    // Разворачиваем каждый товар с учётом количества:
    // каждый экземпляр товара = отдельная позиция в чеке (quantity=1)
    const services = [];
    for (const item of items) {
        const qty = item.quantity || 1;
        for (let i = 0; i < qty; i++) {
            services.push({
                name: `${item.name}, id=${item.id}, Заказ №${orderId}`,
                amount: Number(item.price.toFixed(2)),
                quantity: 1
            });
        }
    }

    if (services.length === 0) {
        console.error("Нет товаров для чека");
        process.exit(1);
    }

    const totalAmount = services.reduce((sum, s) => sum + s.amount, 0);

    const MAX_RETRIES = 3;
    let receiptId = null;
    let lastError = null;

    for (let attempt = 1; attempt <= MAX_RETRIES; attempt++) {
        try {
            // Передаём массив позиций — API поддерживает несколько позиций в одном чеке
            receiptId = await nalogApi.addIncome(services);

            const printLink = `https://lknpd.nalog.ru/api/v1/receipt/${INN}/${receiptId}/print`;

            const result = {
                success: true,
                orderId: orderId,
                receiptId: receiptId,
                printLink: printLink,
                amount: totalAmount,
                appName: APP_NAME,
                inn: INN,
                email: email,
                createdAt: new Date().toISOString()
            };

            console.log(JSON.stringify(result));
            process.exit(0);
        } catch (err) {
            lastError = err.message || "Неизвестная ошибка";
            console.error(`Попытка ${attempt} не удалась: ${lastError}`);
            if (attempt < MAX_RETRIES) {
                await new Promise(r => setTimeout(r, 2000));
            }
        }
    }

    // Если все попытки не удались
    const result = {
        success: false,
        orderId: orderId,
        email: email,
        error: lastError
    };
    console.log(JSON.stringify(result));
    process.exit(0);
}

function readStdin() {
    return new Promise((resolve) => {
        const rl = createInterface({ input: process.stdin });
        let data = '';
        rl.on('line', (line) => { data += line; });
        rl.on('close', () => resolve(data.trim()));
    });
}

main();