import express from "express";
import nalogApi from "../services/nalog.js";

const router = express.Router();

router.get("/health", async (req, res) => {

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

export default router;