import 'package:flutter/material.dart';

import '../../../app/theme.dart';

/// Éléments visuels partagés par le tunnel de commande, les écrans de
/// paiement et le suivi des commandes (maquettes OVANIE).
const Color payNavy = Color(0xFF0A1B4F);
const Color payMuted = Color(0xFF6B7591);
const Color payBorder = Color(0xFFE3E8F0);
const Color paySoft = Color(0xFFF1F4F8);
const Color payBlue = Color(0xFF0B5ED7);
const Color payGreen = Color(0xFF16A34A);
const Color payGreenDark = Color(0xFF15803D);
const Color payGreenSoft = Color(0xFFE6F6EC);
const Color payRed = Color(0xFFE5231B);
const Color payAmber = Color(0xFFF59E0B);

/// Code opérateur (wave, orange, mtn, moov, card) déduit d'un libellé ou d'un
/// fournisseur renvoyé par le serveur ; chaîne vide si inconnu.
String operatorCodeOf(String text) {
  final value = text.toLowerCase();
  if (value.contains('wave')) return 'wave';
  if (value.contains('orange')) return 'orange';
  if (value.contains('mtn')) return 'mtn';
  if (value.contains('moov')) return 'moov';
  if (value.contains('card') || value.contains('carte') || value.contains('visa')) return 'card';
  return '';
}

String operatorLabelOf(String code) {
  switch (code) {
    case 'wave':
      return 'Wave';
    case 'orange':
      return 'Orange Money';
    case 'mtn':
      return 'MTN MoMo';
    case 'moov':
      return 'Moov Money';
    case 'card':
      return 'Carte bancaire';
    default:
      return 'Paiement en ligne';
  }
}

/// Pastille verte « Paiement sécurisé ».
class PaymentSecureChip extends StatelessWidget {
  final String label;

  const PaymentSecureChip({super.key, this.label = 'Paiement sécurisé'});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
    decoration: BoxDecoration(color: payGreenSoft, borderRadius: BorderRadius.circular(20)),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        const Icon(Icons.lock_rounded, color: payGreenDark, size: 16),
        const SizedBox(width: 6),
        Text(
          label,
          style: const TextStyle(color: payGreenDark, fontSize: 12.5, fontWeight: FontWeight.w600),
        ),
      ],
    ),
  );
}

/// Logo rond de l'opérateur ; Visa + Mastercard pour la carte bancaire.
class PaymentOperatorLogo extends StatelessWidget {
  final String code;
  final double size;

  const PaymentOperatorLogo({super.key, required this.code, this.size = 50});

  @override
  Widget build(BuildContext context) {
    if (const {'wave', 'orange', 'mtn', 'moov'}.contains(code)) {
      return Image.asset('assets/images/checkout/op_$code.png', width: size, height: size);
    }
    if (code == 'card') {
      return Container(
        width: size * 1.55,
        height: size,
        padding: EdgeInsets.symmetric(horizontal: size * .14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(size),
          border: Border.all(color: const Color(0xFFE6EAF0)),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Flexible(child: Image.asset('assets/images/operators/visa.png', height: size * .3)),
            SizedBox(width: size * .06),
            Flexible(child: Image.asset('assets/images/operators/mastercard.png', height: size * .38)),
          ],
        ),
      );
    }
    return Container(
      width: size,
      height: size,
      decoration: const BoxDecoration(color: paySoft, shape: BoxShape.circle),
      child: Icon(Icons.payments_outlined, color: payNavy, size: size * .5),
    );
  }
}

/// En-tête : retour à gauche, titre centré, pastille ou action à droite.
class PaymentFlowHeader extends StatelessWidget {
  final String title;
  final VoidCallback? onBack;
  final String? backLabel;
  final IconData backIcon;
  final Widget? trailing;

  const PaymentFlowHeader({
    super.key,
    required this.title,
    this.onBack,
    this.backLabel,
    this.backIcon = Icons.arrow_back_rounded,
    this.trailing = const PaymentSecureChip(),
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 64,
      child: Row(
        children: [
          const SizedBox(width: 4),
          if (onBack != null)
            InkWell(
              onTap: onBack,
              borderRadius: BorderRadius.circular(10),
              child: Padding(
                padding: const EdgeInsets.all(10),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(backIcon, color: payNavy, size: 28),
                    if (backLabel != null) ...[
                      const SizedBox(width: 8),
                      Text(
                        backLabel!,
                        style: const TextStyle(color: payNavy, fontSize: 17, fontWeight: FontWeight.w700),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          Expanded(
            child: FittedBox(
              fit: BoxFit.scaleDown,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 6),
                child: Text(
                  title,
                  maxLines: 1,
                  style: const TextStyle(color: payNavy, fontSize: 18, fontWeight: FontWeight.w800),
                ),
              ),
            ),
          ),
          if (trailing != null) trailing!,
          const SizedBox(width: 14),
        ],
      ),
    );
  }
}

/// Carte blanche bordée avec titre optionnel.
class PaymentCard extends StatelessWidget {
  final String? title;
  final Widget child;
  final EdgeInsetsGeometry padding;
  final Color color;

  const PaymentCard({
    super.key,
    this.title,
    required this.child,
    this.padding = const EdgeInsets.fromLTRB(16, 16, 16, 12),
    this.color = Colors.white,
  });

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: padding,
    decoration: BoxDecoration(
      color: color,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: payBorder),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (title != null) ...[
          Text(
            title!,
            style: const TextStyle(color: payNavy, fontSize: 18, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 6),
        ],
        child,
      ],
    ),
  );
}

/// Ligne « libellé — valeur » séparée par un filet, valeur à droite.
class PaymentInfoRow extends StatelessWidget {
  final String label;
  final String? value;
  final Widget? valueWidget;
  final bool strong;
  final bool last;
  final double verticalPadding;

  const PaymentInfoRow({
    super.key,
    required this.label,
    this.value,
    this.valueWidget,
    this.strong = false,
    this.last = false,
    this.verticalPadding = 10,
  });

  @override
  Widget build(BuildContext context) => Container(
    padding: EdgeInsets.symmetric(vertical: verticalPadding),
    decoration: BoxDecoration(
      border: last ? null : const Border(bottom: BorderSide(color: payBorder)),
    ),
    child: Row(
      children: [
        Text(label, style: const TextStyle(color: payMuted, fontSize: 14.5)),
        const SizedBox(width: 12),
        Expanded(
          child: Align(
            alignment: Alignment.centerRight,
            child:
                valueWidget ??
                Text(
                  value ?? '—',
                  textAlign: TextAlign.right,
                  style: TextStyle(
                    color: payNavy,
                    fontSize: strong ? 15.5 : 14.5,
                    fontWeight: strong ? FontWeight.w800 : FontWeight.w500,
                  ),
                ),
          ),
        ),
      ],
    ),
  );
}

/// Pastille de statut (Payé, En attente, Échoué, À la livraison).
class PaymentStatusBadge extends StatelessWidget {
  final String label;
  final IconData icon;
  final Color color;
  final Color background;

  const PaymentStatusBadge({
    super.key,
    required this.label,
    required this.icon,
    required this.color,
    required this.background,
  });

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
    decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(20)),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, color: color, size: 19),
        const SizedBox(width: 6),
        Text(
          label,
          style: TextStyle(color: color, fontSize: 14, fontWeight: FontWeight.w600),
        ),
      ],
    ),
  );
}

/// Encadré gris d'information avec pastille « i ».
class PaymentInfoNote extends StatelessWidget {
  final String text;
  final Color background;

  const PaymentInfoNote({super.key, required this.text, this.background = paySoft});

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
    decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(12)),
    child: Row(
      children: [
        const Icon(Icons.info_rounded, color: Color(0xFF7C8598), size: 32),
        const SizedBox(width: 14),
        Expanded(
          child: Text(text, style: const TextStyle(color: Color(0xFF4A556E), fontSize: 13.5, height: 1.4)),
        ),
      ],
    ),
  );
}

/// Carte « Besoin d'aide ? » avec le bouton « Contacter le support ».
class PaymentHelpCard extends StatelessWidget {
  final VoidCallback onContact;

  const PaymentHelpCard({super.key, required this.onContact});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: payBorder),
    ),
    child: Row(
      children: [
        Container(
          width: 54,
          height: 54,
          decoration: const BoxDecoration(color: paySoft, shape: BoxShape.circle),
          child: const Icon(Icons.headset_mic_outlined, color: payNavy, size: 28),
        ),
        const SizedBox(width: 12),
        const Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Besoin d’aide ?',
                style: TextStyle(color: payNavy, fontSize: 15, fontWeight: FontWeight.w800),
              ),
              SizedBox(height: 2),
              Text('Notre équipe peut vous accompagner.', style: TextStyle(color: payMuted, fontSize: 12.5)),
            ],
          ),
        ),
        const SizedBox(width: 8),
        OutlinedButton(
          onPressed: onContact,
          style: OutlinedButton.styleFrom(
            foregroundColor: payNavy,
            side: const BorderSide(color: payNavy, width: 1.3),
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
          ),
          child: const Text('Contacter le support', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
        ),
      ],
    ),
  );
}

/// Bouton plein orange ou contour marine, pleine largeur.
class PaymentButton extends StatelessWidget {
  final String label;
  final VoidCallback? onPressed;
  final bool outlined;
  final bool loading;
  final double height;

  const PaymentButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.outlined = false,
    this.loading = false,
    this.height = 54,
  });

  @override
  Widget build(BuildContext context) {
    final child = loading
        ? SizedBox(
            width: 22,
            height: 22,
            child: CircularProgressIndicator(strokeWidth: 2.4, color: outlined ? payNavy : Colors.white),
          )
        : FittedBox(
            fit: BoxFit.scaleDown,
            child: Text(label, maxLines: 1, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
          );
    return SizedBox(
      width: double.infinity,
      height: height,
      child: outlined
          ? OutlinedButton(
              onPressed: loading ? null : onPressed,
              style: OutlinedButton.styleFrom(
                foregroundColor: payNavy,
                side: const BorderSide(color: payNavy, width: 1.5),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: child,
            )
          : FilledButton(
              onPressed: loading ? null : onPressed,
              style: FilledButton.styleFrom(
                elevation: 0,
                backgroundColor: OvanieColors.orange,
                disabledBackgroundColor: OvanieColors.orange.withValues(alpha: .6),
                foregroundColor: Colors.white,
                disabledForegroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: child,
            ),
    );
  }
}

/// Étapes du tunnel (Livraison, Paiement, Confirmation) vues après la commande.
/// [paymentState] : 'done', 'active' (en attente) ou 'error'.
class PaymentFlowSteps extends StatelessWidget {
  final String paymentState;
  final bool confirmed;

  const PaymentFlowSteps({super.key, required this.paymentState, required this.confirmed});

  @override
  Widget build(BuildContext context) {
    Widget dot({required bool done, bool active = false, bool error = false}) {
      final Color color = error
          ? payRed
          : done
          ? payGreenDark
          : active
          ? OvanieColors.orange
          : const Color(0xFFEDF0F5);
      return Container(
        width: 34,
        height: 34,
        decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        child: Icon(
          error
              ? Icons.close_rounded
              : done
              ? Icons.check_rounded
              : Icons.more_horiz_rounded,
          color: done || active || error ? Colors.white : const Color(0xFF8A93A8),
          size: 21,
        ),
      );
    }

    Widget line(bool done) =>
        Expanded(child: Container(height: 2.5, color: done ? payGreenDark : const Color(0xFFDDE2EA)));

    final paymentDone = paymentState == 'done';
    const labels = ['Livraison', 'Paiement', 'Confirmation'];
    final colors = [
      payMuted,
      paymentState == 'error' ? payRed : (paymentState == 'active' ? OvanieColors.orange : payMuted),
      payMuted,
    ];
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 38),
          child: Row(
            children: [
              dot(done: true),
              line(true),
              dot(done: paymentDone, active: paymentState == 'active', error: paymentState == 'error'),
              line(paymentDone),
              dot(done: confirmed),
            ],
          ),
        ),
        const SizedBox(height: 6),
        Row(
          children: [
            for (var i = 0; i < labels.length; i++)
              Expanded(
                child: Text(
                  labels[i],
                  textAlign: TextAlign.center,
                  style: TextStyle(color: colors[i], fontSize: 14),
                ),
              ),
          ],
        ),
      ],
    );
  }
}

/// Vignettes des produits de la commande (jusqu'à trois).
class OrderItemThumbnails extends StatelessWidget {
  final List<String> imageUrls;
  final double height;

  const OrderItemThumbnails({super.key, required this.imageUrls, this.height = 76});

  @override
  Widget build(BuildContext context) {
    final urls = imageUrls.take(3).toList(growable: false);
    return Row(
      children: [
        for (var i = 0; i < 3; i++) ...[
          Expanded(
            child: i < urls.length ? OrderItemThumbnail(url: urls[i], height: height) : SizedBox(height: height),
          ),
          if (i != 2) const SizedBox(width: 8),
        ],
      ],
    );
  }
}

class OrderItemThumbnail extends StatelessWidget {
  final String url;
  final double height;
  final double? width;

  const OrderItemThumbnail({super.key, required this.url, this.height = 76, this.width});

  @override
  Widget build(BuildContext context) {
    const placeholder = Icon(Icons.inventory_2_outlined, color: Color(0xFFADB5C5));
    return ClipRRect(
      borderRadius: BorderRadius.circular(8),
      child: Container(
        width: width,
        height: height,
        color: const Color(0xFFF3F4F6),
        child: url.trim().isEmpty
            ? placeholder
            : Image.network(url, fit: BoxFit.cover, cacheWidth: 300, errorBuilder: (_, __, ___) => placeholder),
      ),
    );
  }
}

const List<String> _shortMonths = [
  'janv.',
  'févr.',
  'mars',
  'avr.',
  'mai',
  'juin',
  'juil.',
  'août',
  'sept.',
  'oct.',
  'nov.',
  'déc.',
];

/// « 12 sept. 2026 »
String paymentShortDate(DateTime? date) {
  if (date == null) return '—';
  final local = date.toLocal();
  return '${local.day} ${_shortMonths[local.month - 1]} ${local.year}';
}

/// « 12 sept. 2026 à 10:24 »
String paymentDateTime(DateTime? date, {String separator = ' à '}) {
  if (date == null) return '—';
  final local = date.toLocal();
  final hour = local.hour.toString().padLeft(2, '0');
  final minute = local.minute.toString().padLeft(2, '0');
  return '${paymentShortDate(local)}$separator$hour:$minute';
}

/// Message sombre flottant avec pastille (succès en vert, erreur en rouge).
void showOvanieToast(BuildContext context, String message, {bool error = false}) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        backgroundColor: const Color(0xFF23262F),
        elevation: 0,
        margin: const EdgeInsets.fromLTRB(16, 0, 16, 12),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        content: Row(
          children: [
            Container(
              width: 28,
              height: 28,
              decoration: BoxDecoration(color: error ? payRed : payGreen, shape: BoxShape.circle),
              child: Icon(error ? Icons.priority_high_rounded : Icons.check_rounded, color: Colors.white, size: 19),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                message,
                style: const TextStyle(color: Colors.white, fontSize: 14.5, fontWeight: FontWeight.w500),
              ),
            ),
          ],
        ),
      ),
    );
}

/// Fenêtre de confirmation centrée : visuel rond, titre, texte, bouton
/// principal coloré et action secondaire.
Future<bool> showOvanieConfirmDialog(
  BuildContext context, {
  required Widget visual,
  required String title,
  required String message,
  required String confirmLabel,
  required Color confirmColor,
  required String cancelLabel,
  bool cancelOutlined = false,
}) async {
  final result = await showDialog<bool>(
    context: context,
    builder: (dialogContext) => Dialog(
      backgroundColor: Colors.white,
      surfaceTintColor: Colors.white,
      insetPadding: const EdgeInsets.symmetric(horizontal: 30),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(22, 26, 22, 16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            visual,
            const SizedBox(height: 18),
            Text(
              title,
              textAlign: TextAlign.center,
              style: const TextStyle(color: payNavy, fontSize: 22, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 10),
            Text(
              message,
              textAlign: TextAlign.center,
              style: const TextStyle(color: payMuted, fontSize: 15.5, height: 1.4),
            ),
            const SizedBox(height: 22),
            SizedBox(
              width: double.infinity,
              height: 54,
              child: FilledButton(
                onPressed: () => Navigator.of(dialogContext).pop(true),
                style: FilledButton.styleFrom(
                  elevation: 0,
                  backgroundColor: confirmColor,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                child: Text(confirmLabel, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
              ),
            ),
            const SizedBox(height: 10),
            if (cancelOutlined)
              SizedBox(
                width: double.infinity,
                height: 54,
                child: OutlinedButton(
                  onPressed: () => Navigator.of(dialogContext).pop(false),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: payNavy,
                    side: const BorderSide(color: payNavy, width: 1.5),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  child: Text(cancelLabel, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
                ),
              )
            else
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(false),
                child: Text(cancelLabel, style: const TextStyle(color: payMuted, fontSize: 16.5)),
              ),
            const SizedBox(height: 4),
          ],
        ),
      ),
    ),
  );
  return result == true;
}
