export function success(res, data, status = 200) {

    return res.status(status).json({
        success: true,
        data
    });

}

export function error(res, code, message, status = 500) {

    return res.status(status).json({
        success: false,
        error: {
            code,
            message
        }
    });

}