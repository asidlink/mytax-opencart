import nalogApi from "./nalog.js";

const MAX_RETRIES = 3;

export async function createReceipt(income) {

    for (let i = 1; i <= MAX_RETRIES; i++) {

        try {

            return await nalogApi.addIncome(income);

        } catch (err) {

            console.log(`Попытка ${i} не удалась`);

            if (i === MAX_RETRIES)
                throw err;

            await new Promise(r => setTimeout(r, 2000));

        }

    }

}