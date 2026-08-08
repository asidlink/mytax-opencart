import healthRoute from "./routes/health.js";
import express from "express";
import bodyParser from "body-parser";
import receiptRoute from "./routes/receipt.js";
import dotenv from "dotenv";
import pkg from "lknpd-nalog-api";
import fs from "fs/promises";
import path from "path";
import { fileURLToPath } from "url";

dotenv.config();

const { NalogApi } = pkg;

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
app.use(bodyParser.json());
app.use("/", healthRoute);
app.use("/api/v1", receiptRoute);

const PORT = process.env.PORT || 4000;
const MAX_RETRIES = 3;
const ERROR_FILE = path.join(__dirname, "logs", "error.json");



const nalogApi = new NalogApi({
  inn: process.env.INN,
  password: process.env.PASSWORD
});

async function createReceiptWithRetry(income, retries = MAX_RETRIES) {
  for (let attempt = 1; attempt <= retries; attempt++) {
    try {
      return await nalogApi.addIncome(income);
    } catch (err) {
      console.error(`Попытка ${attempt} не удалась`, err.message || err);
      if (attempt === retries) throw err;
      await new Promise(r => setTimeout(r, 2000));
    }
  }
}

async function saveToErrorFile(errorData) {
  try {
    let errors = [];
    
    try {
      const data = await fs.readFile(ERROR_FILE, "utf8");
      const parsedData = JSON.parse(data);
      if (Array.isArray(parsedData)) {
        errors = parsedData;
      }
    } catch (err) {
    }
    
    errors.push({
      ...errorData,
      timestamp: new Date().toISOString(),
      retryAttempt: 0
    });
    
    await fs.writeFile(ERROR_FILE, JSON.stringify(errors, null, 2));
    console.log(`Ошибка сохранена в ${ERROR_FILE}`);
  } catch (err) {
    console.error("Не удалось сохранить ошибку в файл:", err);
  }
}

/*
app.get("/health", async (req, res) => {

    const result = {
        status: "ok",
        connect_to_fns: "ok"
    };

    try {

        await nalogApi.getUserInfo();

    } catch (err) {

        console.error(err);

        result.status = "error";
        result.connect_to_fns = "error";

    }

    res.json(result);

});





app.post("/api/v1/create-receipt", async (req, res) => {
  try {
    const { api_pass, email, items } = req.body;

    if (api_pass !== process.env.API_PASS) {
      return res.status(401).json({ error: "Unauthorized" });
    }

    if (!email || !Array.isArray(items) || items.length === 0) {
      return res.status(400).json({ error: "Неверные данные" });
    }

    const total = items.reduce(
      (sum, i) => sum + i.price * (i.quantity || 1),
      0
    );

    const income = {
      name: `${process.env.APPNAME}`,
      amount: Number(total.toFixed(2)),
      quantity: 1
    };

    const receiptId = await createReceiptWithRetry(income);

    const printLink = `https://lknpd.nalog.ru/api/v1/receipt/${process.env.INN}/${receiptId}/print`;

res.json({
    success: true,
    receiptId,
    printLink,
    amount: total,
    appName: process.env.APPNAME,
    createdAt: new Date().toISOString(),
    inn: process.env.INN
});

  } catch (err) {
console.error("========== ERROR ==========");
console.error(err);
console.error(err.stack);
console.error("===========================");
    
    const errorData = {
      email: req.body.email,
      items: req.body.items,
      amount: req.body.items.reduce((sum, i) => sum + i.price * (i.quantity || 1), 0),
      error: err.message || "Неизвестная ошибка",
      api_pass: req.body.api_pass
    };
    
    await saveToErrorFile(errorData);
    
    res.status(500).json({ 
      error: "Не удалось создать чек. Данные сохранены для повторной попытки.",
      saved_to_error_file: true
    });
  }
});
*/
app.listen(PORT, () => {
  console.log(`✅ Сервер запущен: http://localhost:${PORT}`);
  console.log(`📁 Файл ошибок: ${ERROR_FILE}`);
});