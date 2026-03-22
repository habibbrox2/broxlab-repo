import axios from 'axios';
import logger from '../utils/scraperLogger.js';

class CaptchaSolver {
    constructor() {
        this.provider = (process.env.CAPTCHA_PROVIDER || '2captcha').toLowerCase();
        this.apiKey = process.env.CAPTCHA_API_KEY || '';
        this.baseUrl = this.provider === 'anti-captcha'
            ? 'https://api.anti-captcha.com'
            : 'https://2captcha.com';
    }

    isEnabled() {
        return !!this.apiKey;
    }

    async solveRecaptcha({ siteKey, pageUrl }) {
        if (!this.isEnabled()) {
            return { success: false, error: 'captcha_disabled' };
        }

        try {
            if (this.provider === 'anti-captcha') {
                const create = await axios.post(`${this.baseUrl}/createTask`, {
                    clientKey: this.apiKey,
                    task: {
                        type: 'NoCaptchaTaskProxyless',
                        websiteURL: pageUrl,
                        websiteKey: siteKey
                    }
                });
                const taskId = create.data?.taskId;
                if (!taskId) throw new Error('captcha_task_create_failed');
                return await this.pollAntiCaptcha(taskId);
            }

            const create = await axios.get(`${this.baseUrl}/in.php`, {
                params: {
                    key: this.apiKey,
                    method: 'userrecaptcha',
                    googlekey: siteKey,
                    pageurl: pageUrl,
                    json: 1
                }
            });
            if (create.data?.status !== 1) throw new Error(create.data?.request || 'captcha_task_create_failed');
            const taskId = create.data.request;
            return await this.poll2Captcha(taskId);
        } catch (error) {
            logger.warn('Captcha solve failed', { error: error?.message });
            return { success: false, error: 'captcha_failed' };
        }
    }

    async poll2Captcha(taskId) {
        for (let i = 0; i < 24; i++) {
            await new Promise(r => setTimeout(r, 5000));
            const res = await axios.get(`${this.baseUrl}/res.php`, {
                params: { key: this.apiKey, action: 'get', id: taskId, json: 1 }
            });
            if (res.data?.status === 1) {
                return { success: true, token: res.data.request };
            }
            if (res.data?.request !== 'CAPCHA_NOT_READY') {
                return { success: false, error: res.data?.request || 'captcha_failed' };
            }
        }
        return { success: false, error: 'captcha_timeout' };
    }

    async pollAntiCaptcha(taskId) {
        for (let i = 0; i < 24; i++) {
            await new Promise(r => setTimeout(r, 5000));
            const res = await axios.post(`${this.baseUrl}/getTaskResult`, {
                clientKey: this.apiKey,
                taskId
            });
            if (res.data?.status === 'ready') {
                return { success: true, token: res.data?.solution?.gRecaptchaResponse };
            }
            if (res.data?.status === 'processing') continue;
            return { success: false, error: res.data?.errorDescription || 'captcha_failed' };
        }
        return { success: false, error: 'captcha_timeout' };
    }
}

export default new CaptchaSolver();
