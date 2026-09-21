/**
 * ======================================================
 * Mobile Money Payment Service – Orange Money Ready
 * Compatible : Orange Money (sandbox & production)
 * ======================================================
 */

const fetch = require("node-fetch");

const ENV = process.env.NODE_ENV || "sandbox"; // "sandbox" ou "production"

const CONFIG = {
  sandbox: {
    clientId: process.env.ORANGE_SANDBOX_CLIENT_ID,
    clientSecret: process.env.ORANGE_SANDBOX_CLIENT_SECRET,
    baseURL: "https://api.sandbox.orange.com/orange-money-webpay/dev" // endpoint sandbox
  },
  production: {
    clientId: process.env.ORANGE_PROD_CLIENT_ID,
    clientSecret: process.env.ORANGE_PROD_CLIENT_SECRET,
    baseURL: "https://api.orange.com/orange-money-webpay/v1" // endpoint prod
  }
};

const { clientId, clientSecret, baseURL } = CONFIG[ENV];

/**
 * Obtenir un token OAuth2 valide
 */
async function getAccessToken() {
  const authString = Buffer.from(`${clientId}:${clientSecret}`).toString("base64");

  const res = await fetch(`${baseURL}/token`, {
    method: "POST",
    headers: {
      "Authorization": `Basic ${authString}`,
      "Content-Type": "application/x-www-form-urlencoded"
    },
    body: "grant_type=client_credentials"
  });

  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(`Erreur OAuth Orange Money : ${err.error || res.statusText}`);
  }

  const data = await res.json();
  return data.access_token;
}

/**
 * Créer une transaction Orange Money
 * @param {Object} params
 *  - amount : nombre
 *  - currency : string, ex: "XOF"
 *  - externalId : string, référence unique
 *  - payer : { msisdn } numéro Mobile Money du client
 *  - payerMessage : string
 *  - payeeNote : string
 *  - callbackUrl : string
 */
async function createPayment({
  amount,
  currency = "XOF",
  externalId,
  payer,
  payerMessage = "Paiement IMOO",
  payeeNote = "IMOO Marketplace",
  callbackUrl
}) {
  if (!amount || !externalId || !payer?.msisdn || !callbackUrl) {
    throw new Error("Paramètres de paiement incomplets");
  }

  const token = await getAccessToken();

  const payload = {
    amount,
    currency,
    externalId,
    payer: { partyIdType: "MSISDN", partyId: payer.msisdn },
    payerMessage,
    payeeNote,
    callbackUrl
  };

  const res = await fetch(`${baseURL}/transactions`, {
    method: "POST",
    headers: {
      "Authorization": `Bearer ${token}`,
      "Content-Type": "application/json"
    },
    body: JSON.stringify(payload)
  });

  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(`Erreur création transaction Orange Money : ${err.errorMessage || res.statusText}`);
  }

  return await res.json();
}

/**
 * Vérifier le statut d’une transaction via son transactionId
 */
async function getTransactionStatus(transactionId) {
  if (!transactionId) throw new Error("TransactionId requis");

  const token = await getAccessToken();

  const res = await fetch(`${baseURL}/transactions/${transactionId}`, {
    method: "GET",
    headers: {
      "Authorization": `Bearer ${token}`
    }
  });

  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(`Erreur récupération statut transaction : ${err.errorMessage || res.statusText}`);
  }

  return await res.json();
}

/**
 * Exemple de callback (webhook) à exposer dans server.js
 * body = { transactionId, status, amount, currency, externalId, payer }
 */
function handleCallback(req, res) {
  const data = req.body;

  console.log("💳 CALLBACK ORANGE MONEY :", data);

  // Ici tu peux :
  // - marquer la commande comme payée
  // - débloquer contact vendeur
  // - envoyer notification SMS ou email
  // Toujours répondre 200 OK à Orange
  res.status(200).send({ received: true });
}

module.exports = {
  createPayment,
  getTransactionStatus,
  handleCallback
};
