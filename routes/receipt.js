import { success, error } from "../utils/response.js";
import express from "express";
import config from "../config/config.js";
import { createReceipt } from "../services/receiptService.js";

const router = express.Router();

router.post("/create-receipt", async (req, res) => {

    try {

	const { api_pass, orderId, email, items } = req.body;

        if (api_pass !== config.apiPass) {

return error(
    res,
    "UNAUTHORIZED",
    "Неверный API пароль",
    401
);
        }

	if (!orderId || !email || !Array.isArray(items) || items.length === 0) {


return error(
    res,
    "INVALID_REQUEST",
    "Некорректные параметры",
    400
);

        }

        const total = items.reduce((sum, item) => {

            return sum + item.price * (item.quantity || 1);

        }, 0);

        const income = {

            name: config.appName,

            amount: Number(total.toFixed(2)),

            quantity: 1

        };

        const receiptId = await createReceipt(income);

        const printLink =
            `https://lknpd.nalog.ru/api/v1/receipt/${config.inn}/${receiptId}/print`;


return success(res, {

    orderId,

    receiptId,

    printLink,

    amount: total,

    appName: config.appName,

    createdAt: new Date().toISOString(),

    inn: config.inn

});

    } catch (err) {

        console.error(err);

return error(
    res,
    "FNS_ERROR",
    err.message
);

    }

});

export default router;