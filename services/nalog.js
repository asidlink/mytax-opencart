import pkg from "lknpd-nalog-api";
import config from "../config/config.js";

const { NalogApi } = pkg;

const nalogApi = new NalogApi({

    inn: config.inn,

    password: config.password

});

export default nalogApi;