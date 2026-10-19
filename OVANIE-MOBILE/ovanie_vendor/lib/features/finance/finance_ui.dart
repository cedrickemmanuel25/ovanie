import 'package:flutter/material.dart';

import '../../core/ui/vendor_mockup_ui.dart';
import '../orders/order_ui.dart';

/// Éléments communs aux écrans Finances : libellés et couleurs des statuts de
/// versement, logo de l'opérateur Mobile Money, pastilles et boutons.

const financeGreen = Color(0xFF168A2B);
const financePurple = Color(0xFF6F41DB);
const financeAmber = Color(0xFF9A5B00);
const financeRed = Color(0xFFD92D20);
const financeSlate = Color(0xFF3F4F78);

String payoutStatusLabel(Object? raw) {
  switch ('${raw ?? ''}'.trim().toLowerCase()) {
    case 'paid':
      return 'Effectué';
    case 'processing':
      return 'En traitement';
    case 'approved':
      // Libéré par l'échéancier : compté dans le solde disponible.
      return 'Disponible';
    case 'pending':
      return 'Planifié';
    case 'waiting_payment':
      return 'Attente paiement';
    case 'waiting_reception':
      return 'Attente réception';
    case 'failed':
      return 'Échoué';
    case 'blocked':
      // « Bloqué » signifie presque toujours que la commande n'est pas encore
      // livrée, pas qu'il y a un incident sur l'argent du vendeur.
      return 'En attente de livraison';
    case 'cancelled':
      return 'Annulé';
    default:
      final text = '${raw ?? ''}'.trim();
      return text.isEmpty ? 'Planifié' : text;
  }
}

Color payoutStatusColor(Object? raw) {
  switch ('${raw ?? ''}'.trim().toLowerCase()) {
    case 'paid':
      return financeGreen;
    case 'processing':
      return financePurple;
    case 'approved':
      return const Color(0xFF1668E3);
    case 'waiting_payment':
    case 'waiting_reception':
    case 'blocked':
      return financeAmber;
    case 'failed':
    case 'cancelled':
      return financeRed;
    default:
      return financeSlate;
  }
}

/// Opérateur Mobile Money d'un versement : clé transmise par le serveur, à
/// défaut déduite du libellé (« Orange Money », « Wave »…).
String payoutOperatorKey(Map<String, dynamic> payout) {
  for (final raw in [payout['operator'], payout['payment_method'], payout['payment_method_label']]) {
    final value = '${raw ?? ''}'.toLowerCase();
    for (final key in const ['orange', 'mtn', 'moov', 'wave']) {
      if (value.contains(key)) return key;
    }
  }
  return '';
}

const payoutOperators = <String, String>{
  'orange': 'Orange Money',
  'mtn': 'MTN Mobile Money',
  'wave': 'Wave',
  'moov': 'Moov Money',
};

/// Numéro Mobile Money lisible : « 07 08 45 12 36 ».
String payoutPhoneLabel(Object? raw) {
  final text = '${raw ?? ''}'.trim();
  final digits = text.replaceAll(RegExp(r'\D'), '');
  final local = digits.length == 13 && digits.startsWith('225') ? digits.substring(3) : digits;
  if (local.length != 10) return text;
  return [for (var i = 0; i < 10; i += 2) local.substring(i, i + 2)].join(' ');
}

/// Logo rond de l'opérateur ; pictogramme générique si l'opérateur est inconnu.
class PayoutOperatorLogo extends StatelessWidget {
  const PayoutOperatorLogo({super.key, required this.operator, this.size = 52});
  final String operator;
  final double size;

  static const _background = <String, Color>{
    'orange': Color(0xFFFFE3CF),
    'mtn': Color(0xFFFECD1B),
    'wave': Color(0xFF7FD6F7),
    'moov': Color(0xFF0067B3),
  };

  @override
  Widget build(BuildContext context) {
    final known = payoutOperators.containsKey(operator);
    return Container(
      width: size,
      height: size,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(color: known ? _background[operator] : Mk.softBlue, shape: BoxShape.circle),
      alignment: Alignment.center,
      child: known
          ? Padding(
              padding: EdgeInsets.all(operator == 'mtn' ? 0 : size * .14),
              child: Image.asset(
                'assets/images/operators/$operator.png',
                fit: BoxFit.contain,
                semanticLabel: payoutOperators[operator],
              ),
            )
          : Icon(Icons.account_balance_wallet_outlined, color: Mk.icon, size: size * .48),
    );
  }
}

/// Pastille de statut : point coloré + libellé.
class PayoutStatusPill extends StatelessWidget {
  const PayoutStatusPill({super.key, required this.status, this.label});
  final Object? status;
  final String? label;

  @override
  Widget build(BuildContext context) {
    final color = payoutStatusColor(status);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(color: color.withValues(alpha: .12), borderRadius: BorderRadius.circular(99)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(width: 7, height: 7, decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
          const SizedBox(width: 6),
          Flexible(
            // Un libellé long rétrécit légèrement au lieu d'être coupé.
            child: FittedBox(
              fit: BoxFit.scaleDown,
              child: Text(
                label ?? payoutStatusLabel(status),
                maxLines: 1,
                style: TextStyle(color: color, fontSize: 12.5, fontWeight: FontWeight.w700),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Montant avec « FCFA » en plus petit, comme sur les maquettes.
class FinanceAmount extends StatelessWidget {
  const FinanceAmount(this.value, {super.key, this.size = 18, this.color = Mk.navy, this.unitColor});
  final Object? value;
  final double size;
  final Color color;
  final Color? unitColor;

  @override
  Widget build(BuildContext context) {
    final text = orderMoney(value);
    final number = text.replaceAll(RegExp(r'\s*FCFA$'), '');
    return FittedBox(
      fit: BoxFit.scaleDown,
      alignment: AlignmentDirectional.centerStart,
      child: Text.rich(
        TextSpan(
          text: number,
          children: [
            TextSpan(
              text: ' FCFA',
              style: TextStyle(fontSize: size * .7, color: unitColor ?? color, fontWeight: FontWeight.w700),
            ),
          ],
        ),
        maxLines: 1,
        style: TextStyle(color: color, fontSize: size, fontWeight: FontWeight.w800, letterSpacing: -.3),
      ),
    );
  }
}

/// Pictogramme dans un disque de couleur douce.
class FinanceIconDisc extends StatelessWidget {
  const FinanceIconDisc(this.icon, {super.key, required this.color, this.size = 44});
  final IconData icon;
  final Color color;
  final double size;

  @override
  Widget build(BuildContext context) => Container(
        width: size,
        height: size,
        decoration: BoxDecoration(color: color.withValues(alpha: .13), shape: BoxShape.circle),
        child: Icon(icon, color: color, size: size * .52),
      );
}

/// Bouton d'action secondaire à contour (bas des écrans Finances).
class FinanceActionButton extends StatelessWidget {
  const FinanceActionButton({super.key, required this.label, required this.icon, required this.onTap, this.iconColor = Mk.orange});
  final String label;
  final IconData icon;
  final VoidCallback? onTap;
  final Color iconColor;

  @override
  Widget build(BuildContext context) {
    final enabled = onTap != null;
    return Opacity(
      opacity: enabled ? 1 : .45,
      child: Material(
        color: Colors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: const BorderSide(color: Mk.border, width: 1.2),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Container(
            constraints: const BoxConstraints(minHeight: 48),
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(icon, color: iconColor, size: 21),
                const SizedBox(width: 6),
                Flexible(
                  // Un libellé d'un seul mot rétrécit plutôt que d'être coupé en deux.
                  child: label.contains(' ')
                      ? Text(label, maxLines: 2, textAlign: TextAlign.center, style: _labelStyle)
                      : FittedBox(fit: BoxFit.scaleDown, child: Text(label, maxLines: 1, style: _labelStyle)),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  static const _labelStyle = TextStyle(color: Mk.navy, fontSize: 12.5, height: 1.15, fontWeight: FontWeight.w600);
}

/// Ligne « pictogramme · libellé · valeur à droite ».
class FinanceInfoLine extends StatelessWidget {
  const FinanceInfoLine({super.key, required this.icon, required this.label, this.value, this.trailing});
  final IconData icon;
  final String label;
  final String? value;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 7),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: Mk.icon, size: 20),
            const SizedBox(width: 10),
            Expanded(
              child: Text(label, style: const TextStyle(color: Mk.muted, fontSize: 13.5, fontWeight: FontWeight.w500)),
            ),
            const SizedBox(width: 10),
            trailing ??
                Expanded(
                  child: Text(
                    value ?? '—',
                    textAlign: TextAlign.end,
                    style: const TextStyle(color: Mk.navy, fontSize: 13.5, fontWeight: FontWeight.w700),
                  ),
                ),
          ],
        ),
      );
}

/// Poignée + titre d'une feuille du bas.
class FinanceSheet extends StatelessWidget {
  const FinanceSheet({super.key, required this.children});
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => MkTheme(
        lightStatusBar: false,
        child: SafeArea(
          top: false,
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 18),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Center(
                  child: Container(
                    width: 44,
                    height: 5,
                    decoration: BoxDecoration(color: const Color(0xFFD5DBE7), borderRadius: BorderRadius.circular(99)),
                  ),
                ),
                const SizedBox(height: 16),
                ...children,
              ],
            ),
          ),
        ),
      );
}

/// Option à cocher (radio) d'une feuille : carte entière cliquable.
class FinanceRadioRow extends StatelessWidget {
  const FinanceRadioRow({super.key, required this.label, required this.selected, required this.onTap, this.leading});
  final String label;
  final bool selected;
  final VoidCallback onTap;
  final Widget? leading;

  @override
  Widget build(BuildContext context) => Semantics(
        inMutuallyExclusiveGroup: true,
        checked: selected,
        button: true,
        child: Material(
          color: const Color(0xFFF8FAFD),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
            side: const BorderSide(color: Color(0xFFE4E9F1), width: 1.2),
          ),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: onTap,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
              child: Row(
                children: [
                  if (leading != null) ...[leading!, const SizedBox(width: 14)],
                  if (leading == null) ...[FinanceRadio(selected: selected), const SizedBox(width: 12)],
                  Expanded(
                    child: Text(label, style: const TextStyle(color: Mk.navy, fontSize: 15, fontWeight: FontWeight.w600)),
                  ),
                  if (leading != null) FinanceRadio(selected: selected),
                ],
              ),
            ),
          ),
        ),
      );
}

class FinanceRadio extends StatelessWidget {
  const FinanceRadio({super.key, required this.selected});
  final bool selected;

  @override
  Widget build(BuildContext context) => Container(
        width: 24,
        height: 24,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          border: Border.all(color: selected ? Mk.orange : const Color(0xFF8A95AE), width: 2),
        ),
        child: selected
            ? Container(width: 12, height: 12, decoration: const BoxDecoration(color: Mk.orange, shape: BoxShape.circle))
            : null,
      );
}

/// Bandeau « Solde disponible » des maquettes : visuel net du portefeuille et
/// montant réel posé à l'emplacement prévu. [badge] s'affiche en haut à droite,
/// [bottom] (bouton de retrait) se place dans le prolongement bleu du bandeau.
class BalanceBanner extends StatelessWidget {
  const BalanceBanner({super.key, required this.amount, required this.onInfo, this.badge, this.bottom, this.onTap});
  final Object? amount;
  final VoidCallback onInfo;
  final Widget? badge;
  final Widget? bottom;
  final VoidCallback? onTap;

  static const _ratio = 758 / 262;

  @override
  Widget build(BuildContext context) {
    final banner = AspectRatio(
      aspectRatio: _ratio,
      child: Stack(
        fit: StackFit.expand,
        children: [
          Image.asset('assets/images/mockups/fin_banner.jpg', fit: BoxFit.cover, excludeFromSemantics: true),
          LayoutBuilder(
            builder: (context, box) => Stack(
              children: [
                Positioned(
                  left: box.maxWidth * .032,
                  top: box.maxHeight * .33,
                  width: box.maxWidth * .56,
                  height: box.maxHeight * .37,
                  child: Semantics(
                    label: 'Solde disponible : ${orderMoney(amount)}',
                    excludeSemantics: true,
                    child: FinanceAmount(amount, size: 60, color: Colors.white, unitColor: Mk.orange),
                  ),
                ),
              ],
            ),
          ),
          // Zone tactile posée sur le « i » du visuel.
          Align(
            alignment: const FractionalOffset(.30, .165),
            child: SizedBox(
              width: 44,
              height: 44,
              child: Material(
                color: Colors.transparent,
                shape: const CircleBorder(),
                clipBehavior: Clip.antiAlias,
                child: InkWell(onTap: onInfo, child: const Tooltip(message: 'Comprendre mon solde', child: SizedBox.expand())),
              ),
            ),
          ),
          if (badge != null) Positioned(right: 10, top: 10, child: badge!),
        ],
      ),
    );
    return Material(
      borderRadius: BorderRadius.circular(18),
      clipBehavior: Clip.antiAlias,
      color: const Color(0xFF02286A),
      child: InkWell(
        onTap: onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            banner,
            if (bottom != null)
              DecoratedBox(
                decoration: const BoxDecoration(
                  gradient: LinearGradient(colors: [Color(0xFF001841), Color(0xFF043280), Color(0xFF0E52C4), Color(0xFF0A4DC0)]),
                ),
                child: Padding(padding: const EdgeInsets.fromLTRB(14, 2, 14, 14), child: bottom),
              ),
          ],
        ),
      ),
    );
  }
}

/// Argent gagné mais pas encore disponible : planifié, en attente de livraison,
/// de paiement ou de réception.
double financePendingTotal(Map<String, dynamic> summary) => [
  'scheduled',
  'blocked',
  'waiting_payment',
  'waiting_reception',
].fold<double>(0, (total, key) => total + (double.tryParse('${summary[key] ?? 0}') ?? 0));
