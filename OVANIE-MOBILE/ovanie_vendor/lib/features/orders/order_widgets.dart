import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/ui/vendor_mockup_ui.dart';
import '../shell/vendor_tabs.dart';
import 'order_ui.dart';

/// Blocs communs aux écrans de traitement d'une commande : page, en-tête avec
/// frise d'étapes, bloc « Infos », carte de l'adresse et feuille de contact.

String orderValue(Object? value, [String fallback = '—']) {
  final text = '${value ?? ''}'.trim();
  return text.isEmpty || text == 'null' ? fallback : text;
}

bool orderUsesOvanieLogistics(Map<String, dynamic> order) =>
    orderValue(order['delivery_provider'], '').toLowerCase() == 'ovanie';

/// Ouvre un lien avec l'application du téléphone (composeur, cartes, WhatsApp).
Future<bool> orderOpenUrl(String url) async {
  try {
    return await const MethodChannel('ovanie/external_url').invokeMethod<bool>('openUrl', {'url': url}) ?? false;
  } catch (_) {
    return false;
  }
}

/// Numéro ivoirien lisible : « 07 08 45 12 36 ».
String orderPhoneLabel(String phone) {
  final digits = phone.replaceAll(RegExp(r'[^0-9]'), '');
  final local = digits.length == 13 && digits.startsWith('225') ? digits.substring(3) : digits;
  if (local.length != 10) return phone.trim();
  return [for (var i = 0; i < 10; i += 2) local.substring(i, i + 2)].join(' ');
}

/// Structure d'un écran de commande : en-tête blanc, contenu défilant, barre
/// d'actions fixe et barre d'onglets.
class OrderPage extends StatelessWidget {
  const OrderPage({
    super.key,
    required this.title,
    required this.subtitle,
    required this.loading,
    required this.error,
    required this.onRefresh,
    required this.children,
    this.trailing,
    this.actions = const <Widget>[],
    this.hasContent = true,
    this.tab = VendorTab.orders,
    this.emptyMessage = 'Cette commande est indisponible.',
    this.gap = 10,
    this.showLogo = false,
  });

  final String title;
  final String subtitle;
  final Widget? trailing;
  final bool loading;
  final String? error;
  final Future<void> Function() onRefresh;
  final List<Widget> children;
  final List<Widget> actions;

  /// Faux tant qu'aucune donnée de commande n'est disponible.
  final bool hasContent;

  /// Onglet actif de la barre du bas (les écrans Finances réutilisent cette page).
  final VendorTab tab;
  final String emptyMessage;

  /// Espace inséré entre les blocs ; 0 lorsque les blocs gèrent leur propre marge.
  final double gap;

  /// Affiche le logo OVANIE Vendeur à droite du titre (écrans du Menu).
  final bool showLogo;

  @override
  Widget build(BuildContext context) {
    final Widget body;
    if (loading && !hasContent) {
      body = const Center(child: CircularProgressIndicator(color: Mk.orange));
    } else if (!hasContent) {
      body = Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            MkNotice(error ?? emptyMessage),
            const SizedBox(height: 14),
            MkBarButton(label: 'Réessayer', filled: true, onTap: onRefresh),
          ],
        ),
      );
    } else {
      body = RefreshIndicator(
        color: Mk.orange,
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 16),
          children: [
            if (error != null) ...[
              MkNotice(error!),
              const SizedBox(height: 10),
            ],
            for (var i = 0; i < children.length; i++) ...[
              if (i > 0 && gap > 0) SizedBox(height: gap),
              children[i],
            ],
          ],
        ),
      );
    }

    return MkTheme(
      lightStatusBar: false,
      child: Scaffold(
        backgroundColor: orderPage,
        body: Column(
          children: [
            MkPageHeader(
              title: title,
              subtitle: subtitle.isEmpty ? null : subtitle,
              trailing: trailing ??
                  (showLogo && MediaQuery.sizeOf(context).width >= 360
                      ? Image.asset('assets/images/mockups/logo_navy.png', height: 26, semanticLabel: 'OVANIE Vendeur')
                      : null),
            ),
            if (loading && hasContent)
              const LinearProgressIndicator(minHeight: 2, color: Mk.orange, backgroundColor: Color(0xFFFFEEE5)),
            Expanded(child: body),
          ],
        ),
        bottomNavigationBar: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (hasContent && actions.isNotEmpty) MkActionBar(children: actions),
            VendorBottomBar(selected: tab),
          ],
        ),
      ),
    );
  }
}

/// Carte d'en-tête : numéro, date et frise des quatre étapes.
class OrderHeadCard extends StatelessWidget {
  const OrderHeadCard({super.key, required this.order, required this.currentStep});

  final Map<String, dynamic> order;
  final int currentStep;

  @override
  Widget build(BuildContext context) {
    final date = orderShortDateTime(order['created_at']);
    return OrderCard(
      padding: const EdgeInsets.fromLTRB(10, 12, 10, 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4),
            child: Wrap(
              crossAxisAlignment: WrapCrossAlignment.center,
              spacing: 12,
              runSpacing: 2,
              children: [
                Text(
                  orderNumberLabel(order['order_number']),
                  style: const TextStyle(color: Mk.navy, fontSize: 19, fontWeight: FontWeight.w800, letterSpacing: -.3),
                ),
                if (date.isNotEmpty)
                  Text(date, style: const TextStyle(color: Mk.muted, fontSize: 12.5, fontWeight: FontWeight.w500)),
              ],
            ),
          ),
          const SizedBox(height: 12),
          OrderProgressStepper(
            currentStep: currentStep,
            compact: true,
            lastLabel: orderUsesOvanieLogistics(order) ? 'Remise à OVANIE' : 'Livrée',
          ),
        ],
      ),
    );
  }
}

/// Bloc « Infos » : client, adresse de livraison et mode de livraison.
///
/// Avec OVANIE Logistics, le serveur ne transmet ni le téléphone ni l'adresse
/// exacte du client : seule la zone de livraison est affichée.
class OrderInfosCard extends StatelessWidget {
  const OrderInfosCard({super.key, required this.order});

  final Map<String, dynamic> order;

  @override
  Widget build(BuildContext context) {
    final ovanie = orderUsesOvanieLogistics(order);
    final client = orderMap(order['client']);
    final address = orderMap(order['delivery_address']);
    final tracking = orderMap(order['tracking']);
    final phone = orderValue(client['phone'], orderValue(address['recipient_phone'], ''));
    final place = orderValue(address['formatted'], orderValue(address['address'], ''));
    final latitude = double.tryParse('${address['latitude']}');
    final longitude = double.tryParse('${address['longitude']}');
    final estimated = orderShortDate(tracking['estimated_delivery_at']);

    return OrderCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const OrderSectionTitle('Infos'),
          const SizedBox(height: 10),
          OrderInfoTile(
            icon: Icons.person_outline_rounded,
            label: 'Client',
            value: orderValue(address['site_name'], orderValue(client['name'], 'Client')),
            secondary: phone.isEmpty ? null : orderPhoneLabel(phone),
          ),
          const Divider(height: 22, color: orderBorder),
          OrderInfoTile(
            icon: Icons.location_on_outlined,
            label: ovanie ? 'Zone de livraison' : 'Adresse de livraison',
            value: place.isEmpty ? 'Non renseignée' : place,
            below: !ovanie && latitude != null && longitude != null
                ? _MapButton(
                    onTap: () => showOrderMapDialog(
                      context,
                      address: place,
                      mapUrl: orderValue(tracking['delivery_map_url'], ''),
                      latitude: latitude,
                      longitude: longitude,
                    ),
                  )
                : null,
          ),
          const Divider(height: 22, color: orderBorder),
          OrderInfoTile(
            icon: Icons.local_shipping_outlined,
            label: 'Mode de livraison',
            value: orderValue(order['delivery_provider_label'], 'À définir'),
            below: estimated.isEmpty
                ? null
                : Row(
                    children: [
                      const Icon(Icons.calendar_month_outlined, color: Mk.icon, size: 22),
                      const SizedBox(width: 10),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Livraison estimée', style: TextStyle(color: Mk.muted, fontSize: 12, fontWeight: FontWeight.w500)),
                          Text(estimated, style: const TextStyle(color: Mk.navy, fontSize: 13.5, fontWeight: FontWeight.w700)),
                        ],
                      ),
                    ],
                  ),
          ),
          if (ovanie)
            const Padding(
              padding: EdgeInsets.only(top: 12),
              child: Text(
                'OVANIE Logistics gère le contact avec le client et la livraison.',
                style: TextStyle(color: Mk.muted, fontSize: 12.5, fontWeight: FontWeight.w500),
              ),
            ),
        ],
      ),
    );
  }
}

class _MapButton extends StatelessWidget {
  const _MapButton({required this.onTap});
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(8),
        side: const BorderSide(color: orderBlue, width: 1.2),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: const Padding(
          padding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.map_outlined, color: orderBlue, size: 19),
              SizedBox(width: 8),
              Text('Voir sur la carte', style: TextStyle(color: orderBlue, fontSize: 13, fontWeight: FontWeight.w700)),
            ],
          ),
        ),
      ),
    );
  }
}

/// Fenêtre « Adresse de livraison » : carte, adresse et ouverture dans l'application de cartes.
Future<void> showOrderMapDialog(
  BuildContext context, {
  required String address,
  required String mapUrl,
  required double latitude,
  required double longitude,
}) {
  return showDialog<void>(
    context: context,
    builder: (dialogContext) => MkTheme(
      lightStatusBar: false,
      child: Dialog(
        insetPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 24),
        backgroundColor: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(16, 18, 16, 8),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text('Adresse de livraison', style: TextStyle(color: Mk.navy, fontSize: 18, fontWeight: FontWeight.w800)),
              const SizedBox(height: 12),
              ClipRRect(
                borderRadius: BorderRadius.circular(10),
                child: AspectRatio(
                  aspectRatio: 16 / 11,
                  child: OrderMapImage(url: mapUrl),
                ),
              ),
              const SizedBox(height: 12),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(Icons.location_on_outlined, color: Mk.icon, size: 22),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      address.isEmpty ? 'Position GPS du client' : address,
                      style: const TextStyle(color: Mk.navy, fontSize: 14, height: 1.35, fontWeight: FontWeight.w500),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              MkBarButton(
                label: 'Ouvrir l’itinéraire',
                icon: Icons.directions_outlined,
                color: orderBlue,
                height: 44,
                onTap: () async {
                  final opened = await orderOpenUrl(
                    'https://www.google.com/maps/dir/?api=1&destination=$latitude,$longitude',
                  );
                  if (!opened && dialogContext.mounted) {
                    ScaffoldMessenger.of(dialogContext).showSnackBar(
                      const SnackBar(content: Text('Impossible d’ouvrir l’application de cartes sur ce téléphone.')),
                    );
                  }
                },
              ),
              Align(
                alignment: Alignment.centerRight,
                child: TextButton(
                  onPressed: () => Navigator.pop(dialogContext),
                  style: TextButton.styleFrom(
                    foregroundColor: const Color(0xFFE85400),
                    minimumSize: const Size(48, 48),
                    textStyle: const TextStyle(fontFamily: Mk.font, fontSize: 15, fontWeight: FontWeight.w700),
                  ),
                  child: const Text('Fermer'),
                ),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

/// Image de carte fournie par le serveur ; à défaut, un emplacement neutre qui le dit.
class OrderMapImage extends StatelessWidget {
  const OrderMapImage({super.key, required this.url});
  final String url;

  @override
  Widget build(BuildContext context) {
    const unavailable = ColoredBox(
      color: Color(0xFFEAF0F7),
      child: Center(
        child: Padding(
          padding: EdgeInsets.all(16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.map_outlined, color: Mk.hint, size: 36),
              SizedBox(height: 8),
              Text(
                'Aperçu de la carte indisponible',
                textAlign: TextAlign.center,
                style: TextStyle(fontFamily: Mk.font, color: Mk.muted, fontSize: 13, fontWeight: FontWeight.w600),
              ),
            ],
          ),
        ),
      ),
    );
    if (url.isEmpty) return unavailable;
    return Image.network(
      url,
      fit: BoxFit.cover,
      errorBuilder: (_, __, ___) => unavailable,
      loadingBuilder: (context, child, progress) => progress == null
          ? child
          : const ColoredBox(
              color: Color(0xFFEAF0F7),
              child: Center(child: CircularProgressIndicator(color: Mk.orange, strokeWidth: 2.4)),
            ),
    );
  }
}

/// Feuille de contact : nom, numéro en grand, appel et copie.
Future<void> showOrderPhoneSheet(BuildContext context, {required String name, required String phone}) {
  final messenger = ScaffoldMessenger.of(context);
  return showModalBottomSheet<void>(
    context: context,
    backgroundColor: Colors.transparent,
    builder: (sheetContext) => MkTheme(
      lightStatusBar: false,
      child: Container(
        decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(26))),
        child: SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 14),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Center(
                  child: Container(
                    width: 44,
                    height: 5,
                    decoration: BoxDecoration(color: const Color(0xFFC9CED8), borderRadius: BorderRadius.circular(3)),
                  ),
                ),
                const SizedBox(height: 14),
                Center(
                  child: Container(
                    width: 56,
                    height: 56,
                    decoration: const BoxDecoration(color: Color(0xFFDCE8FD), shape: BoxShape.circle),
                    child: const Icon(Icons.call_rounded, color: orderBlue, size: 28),
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  name,
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Mk.navy, fontSize: 16, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 2),
                Text(
                  orderPhoneLabel(phone),
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Mk.navy, fontSize: 26, fontWeight: FontWeight.w800, letterSpacing: .5),
                ),
                const SizedBox(height: 14),
                MkBarButton(
                  label: 'Appeler',
                  icon: Icons.call_rounded,
                  filled: true,
                  onTap: () async {
                    final opened = await orderOpenUrl('tel:${phone.replaceAll(RegExp(r'[^+0-9]'), '')}');
                    if (!sheetContext.mounted) return;
                    if (opened) {
                      Navigator.pop(sheetContext);
                    } else {
                      messenger.showSnackBar(const SnackBar(content: Text('Impossible de lancer l’appel sur ce téléphone.')));
                    }
                  },
                ),
                const SizedBox(height: 8),
                MkBarButton(
                  label: 'Copier le numéro',
                  icon: Icons.copy_rounded,
                  onTap: () async {
                    await Clipboard.setData(ClipboardData(text: phone));
                    if (sheetContext.mounted) Navigator.pop(sheetContext);
                    messenger.showSnackBar(const SnackBar(content: Text('Numéro copié.')));
                  },
                ),
              ],
            ),
          ),
        ),
      ),
    ),
  );
}

/// Case à cocher orange avec son libellé, sur toute la ligne.
class OrderCheckLine extends StatelessWidget {
  const OrderCheckLine({super.key, required this.label, required this.value, required this.onChanged});
  final String label;
  final bool value;
  final ValueChanged<bool>? onChanged;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      checked: value,
      enabled: onChanged != null,
      label: label,
      excludeSemantics: true,
      child: InkWell(
        onTap: onChanged == null ? null : () => onChanged!(!value),
        borderRadius: BorderRadius.circular(8),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 7),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              OrderCheckBox(value: value),
              const SizedBox(width: 12),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.only(top: 2),
                  child: Text(
                    label,
                    style: const TextStyle(color: Mk.navy, fontSize: 13.5, height: 1.3, fontWeight: FontWeight.w500),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class OrderCheckBox extends StatelessWidget {
  const OrderCheckBox({super.key, required this.value});
  final bool value;

  @override
  Widget build(BuildContext context) {
    return AnimatedContainer(
      duration: const Duration(milliseconds: 130),
      width: 24,
      height: 24,
      decoration: BoxDecoration(
        color: value ? Mk.orange : Colors.white,
        borderRadius: BorderRadius.circular(6),
        border: Border.all(color: value ? Mk.orange : Mk.hint, width: 1.6),
      ),
      child: value ? const Icon(Icons.check_rounded, color: Colors.white, size: 18) : null,
    );
  }
}

/// Petite pastille colorée (« En stock », « Prêt », « Stock à vérifier »).
class OrderTag extends StatelessWidget {
  const OrderTag(this.label, {super.key, required this.color});
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
      decoration: BoxDecoration(color: color.withValues(alpha: .13), borderRadius: BorderRadius.circular(99)),
      child: Text(label, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w700)),
    );
  }
}

/// Article d'une commande : photo, nom, précisions, pastille et quantité.
class OrderItemLine extends StatelessWidget {
  const OrderItemLine({
    super.key,
    required this.item,
    required this.quantityLabel,
    this.tag,
    this.leading,
    this.onTap,
  });

  final Map<String, dynamic> item;
  final String quantityLabel;
  final Widget? tag;
  final Widget? leading;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final quantity = int.tryParse('${item['quantity'] ?? 0}') ?? 0;
    final unit = orderUnitLabel(item['unit_label']);
    // Le poids n'est pas répété lorsqu'il figure déjà dans l'unité (« Sac (50 kg) »).
    final weight = orderValue(item['weight_label'], '');
    final meta = [item['brand'], if (!unit.contains(weight)) weight, item['variant_label'], unit]
        .map((e) => orderValue(e, ''))
        .where((e) => e.isNotEmpty)
        .join(' • ');
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Row(
          children: [
            if (leading != null) ...[leading!, const SizedBox(width: 10)],
            OrderProductImage(url: item['image_url'], size: 64),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    orderValue(item['name'], 'Produit'),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: Mk.navy, fontSize: 14, height: 1.2, fontWeight: FontWeight.w700),
                  ),
                  if (meta.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 2),
                      child: Text(
                        meta,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: Mk.muted, fontSize: 12.5, fontWeight: FontWeight.w500),
                      ),
                    ),
                  const SizedBox(height: 5),
                  Wrap(
                    spacing: 10,
                    runSpacing: 4,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      if (tag != null) tag!,
                      Text.rich(
                        TextSpan(
                          text: '$quantityLabel : ',
                          style: const TextStyle(color: Mk.muted, fontWeight: FontWeight.w500),
                          children: [
                            TextSpan(
                              text: unit.isEmpty ? '$quantity' : '$quantity $unit',
                              style: const TextStyle(color: Mk.navy, fontWeight: FontWeight.w800),
                            ),
                          ],
                        ),
                        style: const TextStyle(fontSize: 12.5),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Unité avec majuscule initiale (« Sac », « Barre »), symboles laissés tels quels.
String orderUnitLabel(Object? raw) {
  final unit = orderValue(raw, '');
  if (unit.isEmpty || RegExp(r'^(m²|m³|m2|m3|kg|ml|g|l)\b').hasMatch(unit)) return unit;
  return unit[0].toUpperCase() + unit.substring(1);
}
