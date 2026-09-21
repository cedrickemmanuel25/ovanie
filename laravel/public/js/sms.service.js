/**
 * ======================================================
 * SMS Service – Multi-provider Ready
 * Compatible : Orange SMS | MTN SMS | Twilio | Autres
 * MODE : MOCK (API READY)
 * ======================================================
 */

const fetch = require("node-fetch");

const ENV = process.env.NODE_ENV || "sandbox"; // sandbox ou production

const CONFIG = {
  orange: {
    sandbox: {
      apiKey: process.env.ORANGE_SMS_SANDBOX_KEY,
      baseURL: "https://api.sandbox.orange.com/smsmessaging/v1/outbound"
    },
    production: {
      apiKey: process.env.ORANGE_SMS_PROD_KEY,
      baseURL: "https://api.orange.com/smsmessaging/v1/outbound"
    }
  },
  mtn: {
    sandbox: {
      apiKey: process.env.MTN_SMS_SANDBOX_KEY,
      baseURL: "https://sandbox.mtn.com/sms/v1/messages"
    },
    production: {
      apiKey: process.env.MTN_SMS_PROD_KEY,
      baseURL: "https://api.mtn.com/sms/v1/messages"
    }
  },
  twilio: {
    accountSid: process.env.TWILIO_ACCOUNT_SID,
    authToken: process.env.TWILIO_AUTH_TOKEN,
    from: process.env.TWILIO_FROM_NUMBER
  }
};

/**
 * Envoi SMS
 * @param {string} phone
 * @param {string} message
 * @param {string} provider "orange" | "mtn" | "twilio" | "mock"
 */
async function sendSMS(phone, message, provider = "mock") {
  if (!phone || !message) {
    return { success: false, error: "Téléphone ou message manquant" };
  }

  switch (provider) {
    case "orange": {
      const conf = CONFIG.orange[ENV];
      try {
        // Exemple simplifié, adapter selon API Orange SMS
        const res = await fetch(`${conf.baseURL}/smsmessaging/outbound/tel%3A%2B${phone}/requests`, {
          method: "POST",
          headers: {
            "Authorization": `Bearer ${conf.apiKey}`,
            "Content-Type": "application/json"
          },
          body: JSON.stringify({ outboundSMSMessageRequest: { address: [`tel:+${phone}`], senderAddress: "IMOO", outboundSMSTextMessage: { message } } })
        });
        if (!res.ok) throw new Error(`Erreur Orange SMS : ${res.statusText}`);
        return { success: true, provider: "Orange", sentAt: new Date().toISOString() };
      } catch (err) {
        return { success: false, error: err.message };
      }
    }

    case "mtn": {
      const conf = CONFIG.mtn[ENV];
      try {
        const res = await fetch(conf.baseURL, {
          method: "POST",
          headers: {
            "Authorization": `Bearer ${conf.apiKey}`,
            "Content-Type": "application/json"
          },
          body: JSON.stringify({ to: phone, message })
        });
        if (!res.ok) throw new Error(`Erreur MTN SMS : ${res.statusText}`);
        return { success: true, provider: "MTN", sentAt: new Date().toISOString() };
      } catch (err) {
        return { success: false, error: err.message };
      }
    }

    case "twilio": {
      const { accountSid, authToken, from } = CONFIG.twilio;
      try {
        const auth = Buffer.from(`${accountSid}:${authToken}`).toString("base64");
        const res = await fetch(`https://api.twilio.com/2010-04-01/Accounts/${accountSid}/Messages.json`, {
          method: "POST",
          headers: {
            "Authorization": `Basic ${auth}`,
            "Content-Type": "application/x-www-form-urlencoded"
          },
          body: new URLSearchParams({ To: phone, From: from, Body: message })
        });
        if (!res.ok) throw new Error(`Erreur Twilio SMS : ${res.statusText}`);
        return { success: true, provider: "Twilio", sentAt: new Date().toISOString() };
      } catch (err) {
        return { success: false, error: err.message };
      }
    }

    case "mock":
    default:
      console.log("📩 SMS SENT (MOCK)");
      console.log({ phone, message });
      return { success: true, provider: "MOCK", sentAt: new Date().toISOString() };
  }
}

module.exports = {
  sendSMS
};
