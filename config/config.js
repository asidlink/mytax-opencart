import dotenv from "dotenv";

dotenv.config();

export default {

    port: Number(process.env.PORT),

    apiPass: process.env.API_PASS,

    inn: process.env.INN,

    password: process.env.PASSWORD,

    appName: process.env.APPNAME

};