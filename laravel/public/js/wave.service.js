/**
 * ======================================================
 * Wave Service – QR Code Marchand (RÉEL)
 * ======================================================
 */

const QRCode = require("qrcode");

/**
 * Génère un QR Code Wave marchand réel
 * Le QR redirige vers le compte Wave du marchand
 *
 * IMPORTANT :
 * - Le client saisit le montant lui-même
 * - L’admin confirme ensuite le paiement
 *
 * @param {string} merchantPhone Numéro Wave du marchand (ex: 07xxxxxxxx)
 * @param {string} reference Référence commande IMOO
 * @returns {Object} { qrCodeDataUrl }
 */
async function generateWaveQRCode({ merchantPhone, reference }) {
  try {
    // Format officiel Wave QR (deep link)
    const waveUrl = `https://wave.com/pay/${merchantPhone}?ref=${reference}`;

    const qrCodeDataUrl = await QRCode.toDataURL(waveUrl);

    return {
      qrCodeDataUrl,
      reference
    };
  } catch (error) {
    console.error("Wave QR Generation Error:", error.message);
    throw new Error("Erreur génération QR Code Wave");
  }
}

module.exports = { generateWaveQRCode };
