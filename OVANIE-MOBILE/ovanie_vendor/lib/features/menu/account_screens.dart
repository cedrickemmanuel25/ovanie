import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/network/api_client.dart';
import '../../core/push/push_notification_service.dart';
import '../../core/ui/mk_dialogs.dart';
import '../../core/ui/mk_file_pick.dart';
import '../../core/ui/vendor_mockup_ui.dart' show MkEye;
import '../finance/data_ui.dart' show downloadVendorFile;
import 'delivery_screens.dart';
import 'menu_ui.dart';

const _warn = Color(0xFFE85400);
const _line = Color(0xFFE4E9F1);
const _track = Color(0xFFE6EBF3);

// ============================================================================
// Éléments communs aux écrans « compte et boutique »
// ============================================================================

/// Carte d'en-tête : pastille, titre, étiquette d'état, texte, illustration à
/// droite fondue dans la carte, puis barre d'avancement facultative.
class _HeroCard extends StatelessWidget {
  const _HeroCard({
    required this.icon,
    required this.color,
    required this.title,
    required this.art,
    this.pill,
    this.description = '',
    this.accent,
    this.progress,
    this.progressColor,
    this.progressLabel,
    this.footer,
  });

  final IconData icon;
  final Color color;
  final String title, art, description;
  final Widget? pill, footer;

  /// Liseré coloré sur le bord gauche (état qui demande une action).
  final Color? accent;
  final double? progress;
  final Color? progressColor;
  final String? progressLabel;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
      child: LayoutBuilder(
        builder: (context, box) {
          final artWidth = (box.maxWidth * .4).clamp(120.0, 230.0);
          return Stack(
            children: [
              Positioned(
                top: 14,
                right: 0,
                width: artWidth,
                child: ExcludeSemantics(
                  child: ShaderMask(
                    blendMode: BlendMode.dstIn,
                    shaderCallback: (rect) => const LinearGradient(
                      colors: [Colors.transparent, Colors.white],
                      stops: [0, .22],
                    ).createShader(rect),
                    child: Image.asset(art, fit: BoxFit.fitWidth),
                  ),
                ),
              ),
              if (accent != null) Positioned(left: 0, top: 0, bottom: 0, child: Container(width: 4, color: accent)),
              Padding(
                padding: const EdgeInsets.fromLTRB(14, 14, 14, 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Padding(
                      padding: EdgeInsets.only(right: artWidth * .78),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          MkIconDisc(icon, color: color, size: 42),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  title,
                                  style: const TextStyle(
                                    color: menuBlue,
                                    fontSize: 14.5,
                                    height: 1.2,
                                    fontWeight: FontWeight.w800,
                                    letterSpacing: -.2,
                                  ),
                                ),
                                if (pill != null) ...[const SizedBox(height: 6), pill!],
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    if (description.isNotEmpty)
                      Padding(
                        padding: EdgeInsets.only(top: 10, right: artWidth * .92),
                        child: ConstrainedBox(
                          constraints: BoxConstraints(minHeight: progress == null ? artWidth * .42 : 34),
                          child: Text(
                            description,
                            style: const TextStyle(color: menuMuted, fontSize: 12, height: 1.4, fontWeight: FontWeight.w500),
                          ),
                        ),
                      ),
                    if (progress != null) ...[
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: ClipRRect(
                              borderRadius: BorderRadius.circular(99),
                              child: LinearProgressIndicator(
                                value: progress,
                                minHeight: 7,
                                color: progressColor ?? color,
                                backgroundColor: _track,
                              ),
                            ),
                          ),
                          if (progressLabel != null) ...[
                            const SizedBox(width: 10),
                            Text(
                              progressLabel!,
                              style: const TextStyle(color: menuBlue, fontSize: 12.5, fontWeight: FontWeight.w600),
                            ),
                          ],
                        ],
                      ),
                    ],
                    if (footer != null) ...[const SizedBox(height: 10), footer!],
                  ],
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

/// Pictogramme dans un carré arrondi teinté (en-tête de bloc, document, appareil).
Widget _tile(IconData icon, Color color, {double size = 42, Color? background}) => Container(
  width: size,
  height: size,
  decoration: BoxDecoration(
    color: background ?? color.withValues(alpha: .11),
    borderRadius: BorderRadius.circular(size * .27),
  ),
  child: Icon(icon, color: color, size: size * .54),
);

/// Étiquette d'état à droite d'une carte.
Widget _status(String label, Color color, {IconData? icon, bool soft = false}) => Container(
  padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
  decoration: BoxDecoration(
    color: soft ? const Color(0xFFECEFF5) : color.withValues(alpha: .12),
    borderRadius: BorderRadius.circular(99),
  ),
  child: Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      if (icon != null) ...[Icon(icon, color: color, size: 16), const SizedBox(width: 5)],
      Flexible(
        child: Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w700),
        ),
      ),
    ],
  ),
);

/// Carte blanche à titre précédé d'un pictogramme.
class _Block extends StatelessWidget {
  const _Block({required this.icon, required this.color, required this.title, required this.child, this.trailing});
  final IconData icon;
  final Color color;
  final String title;
  final Widget child;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 10),
    padding: const EdgeInsets.fromLTRB(14, 12, 14, 14),
    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            _tile(icon, color, size: 38),
            const SizedBox(width: 12),
            Expanded(
              child: FittedBox(
                fit: BoxFit.scaleDown,
                alignment: Alignment.centerLeft,
                child: Text(
                  title,
                  style: const TextStyle(color: menuBlue, fontSize: 16, fontWeight: FontWeight.w800, letterSpacing: -.2),
                ),
              ),
            ),
            if (trailing != null) ...[const SizedBox(width: 8), trailing!],
          ],
        ),
        const SizedBox(height: 8),
        child,
      ],
    ),
  );
}

/// Bouton à contour ou plein, avec pictogramme (hauteur compacte des cartes).
Widget _button(
  String label,
  IconData icon,
  VoidCallback? onTap, {
  Color color = Mk.orange,
  Color? text,
  bool filled = false,
  bool loading = false,
  double height = 40,
  bool chevron = false,
}) {
  final foreground = filled ? Colors.white : (text ?? (color == Mk.orange ? _warn : color));
  return Opacity(
    opacity: onTap == null && !loading ? .5 : 1,
    child: Material(
      color: filled ? color : Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(9),
        side: BorderSide(color: color, width: 1.3),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: loading ? null : onTap,
        child: SizedBox(
          height: height,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8),
            child: Row(
              children: [
                if (chevron) const SizedBox(width: 22),
                Expanded(
                  child: Center(
                    child: loading
                        ? SizedBox.square(
                            dimension: 18,
                            child: CircularProgressIndicator(strokeWidth: 2.2, color: foreground),
                          )
                        : FittedBox(
                            fit: BoxFit.scaleDown,
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(icon, color: foreground, size: 20),
                                const SizedBox(width: 8),
                                Text(
                                  label,
                                  style: TextStyle(color: foreground, fontSize: 13.5, fontWeight: FontWeight.w700),
                                ),
                              ],
                            ),
                          ),
                  ),
                ),
                if (chevron) Icon(Icons.chevron_right_rounded, color: foreground, size: 22),
              ],
            ),
          ),
        ),
      ),
    ),
  );
}

// ============================================================================
// État de la boutique
// ============================================================================

/// État de la boutique : statut commercial, points de contrôle, parcours
/// d'activation et ce qu'il reste à faire pour vendre.
class ShopStatusScreen extends StatefulWidget {
  const ShopStatusScreen({super.key});
  @override
  State<ShopStatusScreen> createState() => _ShopStatusState();
}

class _ShopStatusState extends State<ShopStatusScreen> {
  Map<String, dynamic> data = {};
  bool loading = true;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      data = await menuApi('shop/status');
    } catch (e) {
      error = ApiClient.friendlyError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _open(Widget page) async {
    await Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => page));
    if (mounted) load();
  }

  @override
  Widget build(BuildContext context) {
    final status = mapOf(data['status']);
    final tone = '${status['tone'] ?? ''}';
    final open = data['commercial_open'] == true;
    final color = switch (tone) {
      'approved' => menuGreen,
      'rejected' => menuRed,
      'pending' => _warn,
      _ => Mk.icon,
    };
    final icon = switch (tone) {
      'approved' => Icons.check_circle_rounded,
      'rejected' => Icons.block_rounded,
      'pending' => Icons.assignment_late_outlined,
      _ => Icons.storefront_outlined,
    };
    final art = switch (tone) {
      'rejected' => 'status_restricted',
      'inactive' => 'status_closed',
      _ => 'status_open',
    };
    final steps = rowsOf(data['steps']);
    final checks = rowsOf(data['checks']);
    final missing = (data['logistics_missing'] is List ? data['logistics_missing'] as List : const []).map((e) => '$e').toList();
    final products = mapOf(data['products']);
    final reason = screenText(data['rejection_reason'], '');
    final documentsDone = steps.any((s) => s['key'] == 'documents' && s['done'] == true);
    final logisticsDone = steps.any((s) => s['key'] == 'logistics' && s['done'] == true);
    final done = steps.where((s) => s['done'] == true).length;
    final blocked = tone == 'rejected' || tone == 'inactive';

    final todo = <Widget>[
      if (!documentsDone)
        _button(
          'Compléter mes documents',
          Icons.cloud_upload_outlined,
          () => _open(const ShopDocumentsScreen()),
          filled: true,
          height: 46,
        ),
      if (!documentsDone && !logisticsDone) const SizedBox(height: 8),
      if (!logisticsDone)
        _button(
          'Compléter la logistique',
          Icons.local_shipping_outlined,
          () => _open(const DeliverySettingsScreen()),
          filled: documentsDone,
          height: 46,
        ),
    ];

    return MenuPage(
      title: 'État de la boutique',
      subtitle: 'Ce qui permet à votre boutique de vendre sur OVANIE',
      refresh: load,
      loading: loading,
      error: error,
      hasContent: data.isNotEmpty,
      children: [
        _HeroCard(
          icon: icon,
          color: color,
          title: screenText(status['label'], 'Ma boutique'),
          art: 'assets/images/mockups/$art.jpg',
          accent: tone == 'approved' || tone == 'pending' ? color : null,
          pill: _status(
            open ? 'Ouverte aux clients' : 'Fermée aux clients',
            open ? menuGreen : menuRed,
            icon: open ? Icons.storefront_outlined : Icons.lock_outline_rounded,
          ),
          description: screenText(status['description'], ''),
          progress: steps.isEmpty ? null : done / steps.length,
          progressColor: tone == 'inactive' ? Mk.orange : color,
          progressLabel: steps.isEmpty ? null : '$done/${steps.length} étapes',
          footer: reason.isEmpty
              ? null
              : Container(
                  padding: const EdgeInsets.fromLTRB(10, 9, 10, 9),
                  decoration: BoxDecoration(color: const Color(0xFFFDECEC), borderRadius: BorderRadius.circular(10)),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Icon(Icons.warning_rounded, color: menuRed, size: 20),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text.rich(
                          TextSpan(
                            children: [
                              const TextSpan(
                                text: 'Motif indiqué par OVANIE : ',
                                style: TextStyle(fontWeight: FontWeight.w800),
                              ),
                              TextSpan(text: reason),
                            ],
                          ),
                          style: const TextStyle(color: Color(0xFFB42318), fontSize: 12.5, height: 1.4, fontWeight: FontWeight.w500),
                        ),
                      ),
                    ],
                  ),
                ),
        ),
        _Stats(
          cells: [
            (Icons.view_in_ar_outlined, menuAzure, 'Produits actifs', '${number(products['active']).toInt()}'),
            (Icons.grid_view_rounded, Mk.orange, 'Au catalogue', '${number(products['total']).toInt()}'),
            (Icons.event_available_outlined, menuGreen, 'Membre depuis', _since(data['member_since'])),
          ],
        ),
        if (checks.isNotEmpty)
          _Block(
            icon: Icons.checklist_rounded,
            color: Mk.orange,
            title: 'Points de contrôle',
            child: Column(
              children: [
                for (var i = 0; i < checks.length; i++) ...[
                  if (i > 0) const Divider(height: 1, indent: 32, color: _line),
                  _check(checks[i], blocked),
                ],
              ],
            ),
          ),
        if (steps.isNotEmpty)
          _Block(
            icon: Icons.account_tree_outlined,
            color: Mk.orange,
            title: 'Parcours d’activation',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                for (var i = 0; i < steps.length; i++)
                  _step(
                    steps[i],
                    i,
                    last: i == steps.length - 1,
                    current: steps[i]['done'] != true && steps.take(i).every((s) => s['done'] == true),
                    nextDone: i < steps.length - 1 && steps[i + 1]['done'] == true,
                    blocked: blocked,
                  ),
                if (!blocked && missing.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.fromLTRB(10, 9, 10, 9),
                    decoration: BoxDecoration(color: const Color(0xFFE3EEFD), borderRadius: BorderRadius.circular(10)),
                    child: Row(
                      children: [
                        const Icon(Icons.info_rounded, color: menuAzure, size: 22),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text.rich(
                            TextSpan(
                              children: [
                                const TextSpan(
                                  text: 'Logistique à compléter : ',
                                  style: TextStyle(color: menuBlue, fontWeight: FontWeight.w700),
                                ),
                                TextSpan(text: '${missing.join(', ')}.'),
                              ],
                            ),
                            style: const TextStyle(color: menuAzure, fontSize: 12.5, height: 1.4, fontWeight: FontWeight.w500),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
                if (!blocked && todo.isNotEmpty) ...[const SizedBox(height: 10), ...todo],
              ],
            ),
          ),
        if (blocked && todo.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: todo),
          ),
        Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: Material(
            color: Colors.white,
            borderRadius: BorderRadius.circular(14),
            clipBehavior: Clip.antiAlias,
            child: InkWell(
              onTap: () => _open(const ShopDocumentsScreen()),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(14, 12, 10, 12),
                child: Row(
                  children: [
                    menuIcon(Icons.folder_outlined, color: menuPurple, size: 42),
                    const SizedBox(width: 12),
                    const Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Documents de la boutique',
                            style: TextStyle(color: menuBlue, fontSize: 14.5, fontWeight: FontWeight.w700),
                          ),
                          SizedBox(height: 2),
                          Text(
                            'Pièce d’identité, selfie, RCCM et document fiscal',
                            style: TextStyle(color: menuMuted, fontSize: 12.5, height: 1.3, fontWeight: FontWeight.w500),
                          ),
                        ],
                      ),
                    ),
                    const Icon(Icons.chevron_right_rounded, color: Mk.icon),
                  ],
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }

  String _since(dynamic raw) {
    final date = DateTime.tryParse('${raw ?? ''}')?.toLocal();
    if (date == null) return 'Non renseigné';
    const months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    return '${months[date.month - 1]} ${date.year}';
  }

  Widget _check(Map<String, dynamic> c, bool blocked) {
    final ok = c['done'] == true;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 9),
      child: Row(
        children: [
          Icon(ok ? Icons.check_circle_rounded : Icons.error_rounded, color: ok ? menuGreen : _warn, size: 22),
          const SizedBox(width: 10),
          Text(
            screenText(c['label'], ''),
            style: const TextStyle(color: menuBlue, fontSize: 13, fontWeight: FontWeight.w700),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              screenText(c['value'], ''),
              textAlign: TextAlign.right,
              style: TextStyle(
                color: !ok && blocked ? menuRed : menuMuted,
                fontSize: 12,
                height: 1.3,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _step(
    Map<String, dynamic> s,
    int index, {
    required bool last,
    required bool current,
    required bool nextDone,
    required bool blocked,
  }) {
    final ok = s['done'] == true;
    final Widget marker;
    if (ok) {
      marker = const Icon(Icons.check_circle_rounded, color: menuGreen, size: 26);
    } else if (blocked) {
      // Boutique restreinte : l'étape à reprendre est cerclée, les suivantes grisées.
      marker = Container(
        width: 22,
        height: 22,
        margin: const EdgeInsets.all(2),
        decoration: BoxDecoration(
          color: current ? Colors.white : const Color(0xFFDDE3EE),
          shape: BoxShape.circle,
          border: Border.all(color: current ? Mk.orange : const Color(0xFFA9B4CC), width: current ? 6 : 5),
        ),
      );
    } else {
      marker = Container(
        width: 24,
        height: 24,
        margin: const EdgeInsets.all(1),
        alignment: Alignment.center,
        decoration: const BoxDecoration(color: Color(0xFFDFE4EE), shape: BoxShape.circle),
        child: Text(
          '${index + 1}',
          style: const TextStyle(color: Mk.icon, fontSize: 12.5, fontWeight: FontWeight.w800),
        ),
      );
    }
    final faded = !ok && !current && blocked;
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const SizedBox(width: 6),
          SizedBox(
            width: 26,
            child: Column(
              children: [
                marker,
                if (!last)
                  Expanded(
                    child: ok && nextDone
                        ? Container(width: 2.5, color: menuGreen)
                        : CustomPaint(
                            size: const Size(2.5, double.infinity),
                            painter: _DottedLine(const Color(0xFFB8C2D6), dotted: !blocked),
                          ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(top: 3, bottom: last ? 0 : 14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    screenText(s['title'], ''),
                    style: TextStyle(
                      color: faded ? Mk.icon.withValues(alpha: .75) : menuBlue,
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    screenText(s['description'], ''),
                    style: const TextStyle(color: menuMuted, fontSize: 12.5, height: 1.4, fontWeight: FontWeight.w500),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Trait vertical du parcours : plein entre deux étapes faites, pointillé sinon.
class _DottedLine extends CustomPainter {
  const _DottedLine(this.color, {this.dotted = true});
  final Color color;
  final bool dotted;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..strokeWidth = 2.5
      ..strokeCap = StrokeCap.round;
    final x = size.width / 2;
    if (!dotted) {
      canvas.drawLine(Offset(x, 0), Offset(x, size.height), paint);
      return;
    }
    for (var y = 3.0; y < size.height - 1; y += 6) {
      canvas.drawLine(Offset(x, y), Offset(x, y + 1.5), paint);
    }
  }

  @override
  bool shouldRepaint(_DottedLine old) => old.color != color || old.dotted != dotted;
}

/// Trois indicateurs côte à côte : pastille à gauche, libellé et valeur à droite.
class _Stats extends StatelessWidget {
  const _Stats({required this.cells});
  final List<(IconData, Color, String, String)> cells;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: SizedBox(
      height: 60,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (var i = 0; i < cells.length; i++) ...[
            if (i > 0) const SizedBox(width: 8),
            Expanded(
              child: Container(
                padding: const EdgeInsets.fromLTRB(6, 0, 5, 0),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
                child: Row(
                  children: [
                    menuIcon(cells[i].$1, color: cells[i].$2, size: 32),
                    const SizedBox(width: 5),
                    Expanded(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          FittedBox(
                            fit: BoxFit.scaleDown,
                            alignment: Alignment.centerLeft,
                            child: Text(
                              cells[i].$3,
                              style: const TextStyle(color: Mk.icon, fontSize: 10, fontWeight: FontWeight.w500),
                            ),
                          ),
                          const SizedBox(height: 2),
                          FittedBox(
                            fit: BoxFit.scaleDown,
                            alignment: Alignment.centerLeft,
                            child: Text(
                              cells[i].$4,
                              style: const TextStyle(color: menuBlue, fontSize: 16, height: 1.15, fontWeight: FontWeight.w800),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ],
      ),
    ),
  );
}

// ============================================================================
// Documents de la boutique
// ============================================================================

/// Documents de vérification : identité du vendeur et documents de
/// l'entreprise, avec consultation et remplacement depuis le téléphone.
class ShopDocumentsScreen extends StatefulWidget {
  const ShopDocumentsScreen({super.key});
  @override
  State<ShopDocumentsScreen> createState() => _ShopDocumentsState();
}

class _ShopDocumentsState extends State<ShopDocumentsScreen> {
  Map<String, dynamic> data = {};
  bool loading = true;
  String? busy;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      data = await menuApi('shop/documents');
    } catch (e) {
      error = ApiClient.friendlyError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  List<Map<String, dynamic>> get _docs => rowsOf(data['documents']);

  /// Ligne visible : document envoyé, obligatoire, ou documents d'entreprise.
  bool _visible(Map<String, dynamic> d) => d['group'] == 'company' || d['uploaded'] == true || d['required'] == true;

  Future<File?> _photo(String title, String art) async {
    final source = await showMkSheet<ImageSource>(
      context,
      builder: (sheet) => MkSheetFrame(
        children: [
          ExcludeSemantics(
            child: Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxHeight: 170),
                child: Image.asset('assets/images/mockups/$art.jpg', fit: BoxFit.contain),
              ),
            ),
          ),
          const SizedBox(height: 12),
          Text(
            title,
            textAlign: TextAlign.center,
            style: const TextStyle(color: menuBlue, fontSize: 21, height: 1.2, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 6),
          const Text(
            'Photo nette, bien éclairée, sans reflet ni partie coupée.',
            textAlign: TextAlign.center,
            style: TextStyle(color: menuMuted, fontSize: 13.5, height: 1.4, fontWeight: FontWeight.w500),
          ),
          const SizedBox(height: 16),
          MkBarButton(
            label: 'Prendre une photo',
            icon: Icons.photo_camera_outlined,
            filled: true,
            onTap: () => Navigator.pop(sheet, ImageSource.camera),
          ),
          const SizedBox(height: 8),
          MkBarButton(
            label: 'Choisir dans la galerie',
            icon: Icons.image_outlined,
            onTap: () => Navigator.pop(sheet, ImageSource.gallery),
          ),
        ],
      ),
    );
    if (source == null) return null;
    final picked = await ImagePicker().pickImage(source: source, maxWidth: 2200, imageQuality: 88);
    return picked == null ? null : File(picked.path);
  }

  Future<void> _replace(Map<String, dynamic> doc) async {
    final key = '${doc['key']}';
    try {
      String type;
      final files = <String, File>{};
      if (key == 'selfie') {
        final file = await _photo('Selfie du vendeur', 'doc_selfie');
        if (file == null) return;
        type = 'selfie';
        files['document_file'] = file;
      } else if (key.startsWith('identity')) {
        final mode = await _identityMode();
        if (mode == null) return;
        if (mode == 'pdf') {
          if (!mounted) return;
          final file = await pickMkFile(
            context,
            title: 'Pièce d’identité (PDF)',
            message: 'Un seul fichier PDF avec le recto et le verso, 10 Mo maximum.',
            camera: false,
            gallery: false,
            extensions: const ['pdf'],
          );
          if (file == null) return;
          type = 'identity_pdf';
          files['document_file'] = file;
        } else {
          final front = await _photo('Recto de la pièce d’identité', 'doc_recto');
          if (front == null || !mounted) return;
          final back = await _photo('Verso de la pièce d’identité', 'doc_verso');
          if (back == null) return;
          type = 'identity_scan';
          files['identity_file_front'] = front;
          files['identity_file_back'] = back;
        }
      } else {
        final file = await pickMkFile(
          context,
          title: screenText(doc['label'], 'Document'),
          message: 'Photo nette ou PDF lisible, 5 Mo maximum.',
          extensions: const ['pdf'],
        );
        if (file == null) return;
        type = key;
        files['document_file'] = file;
      }
      if (!mounted) return;
      final ok = await showMkConfirmSheet(
        context,
        icon: Icons.upload_file_outlined,
        iconColor: Mk.blue,
        title: doc['uploaded'] == true ? 'Remplacer ce document ?' : 'Envoyer ce document ?',
        equalButtons: true,
        summary: MkSheetSummary(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                screenText(doc['label'], ''),
                style: const TextStyle(color: menuBlue, fontSize: 15, fontWeight: FontWeight.w800),
              ),
              for (final f in files.values) ...[
                const SizedBox(height: 8),
                Row(
                  children: [
                    Icon(
                      f.path.toLowerCase().endsWith('.pdf') ? Icons.picture_as_pdf_outlined : Icons.image_outlined,
                      color: f.path.toLowerCase().endsWith('.pdf') ? menuRed : Mk.icon,
                      size: 22,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        f.uri.pathSegments.last,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: menuMuted, fontSize: 13.5, fontWeight: FontWeight.w500),
                      ),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
        message:
            'OVANIE contrôle le fichier puis relance la vérification de vos documents. Votre boutique reste ouverte pendant ce contrôle.',
        confirmLabel: 'Envoyer',
        confirmIcon: Icons.send_rounded,
      );
      if (!ok || !mounted) return;
      setState(() => busy = key);
      final r = await menuApi('shop/documents', method: 'POST', data: {'document_type': type}, files: files);
      await load();
      if (mounted) {
        mkToast(
          context,
          r['level'] == 'warning' ? screenText(r['message'], 'Document envoyé.') : 'Document envoyé. OVANIE va le vérifier.',
        );
      }
    } catch (e) {
      if (mounted) menuError(context, e);
    } finally {
      if (mounted) setState(() => busy = null);
    }
  }

  /// Feuille « Pièce d'identité » : un PDF, ou deux photos recto puis verso.
  Future<String?> _identityMode() {
    var mode = data['identity_mode'] == 'scan' ? 'scan' : 'pdf';
    Widget option(StateSetter refresh, String value, String art, String label) {
      final selected = mode == value;
      return Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Semantics(
          button: true,
          selected: selected,
          label: label,
          excludeSemantics: true,
          child: Material(
            color: selected ? const Color(0xFFFFF4EC) : Colors.white,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
              side: BorderSide(color: selected ? Mk.orange : const Color(0xFFDCE2EC), width: selected ? 1.5 : 1.2),
            ),
            clipBehavior: Clip.antiAlias,
            child: InkWell(
              onTap: () => refresh(() => mode = value),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(10, 8, 12, 8),
                child: Row(
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(10),
                      child: Image.asset('assets/images/mockups/$art.jpg', width: 64, height: 46, fit: BoxFit.cover),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        label,
                        style: const TextStyle(color: menuBlue, fontSize: 14, height: 1.3, fontWeight: FontWeight.w700),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Icon(
                      selected ? Icons.radio_button_checked_rounded : Icons.radio_button_unchecked_rounded,
                      color: selected ? Mk.orange : const Color(0xFF8A95AE),
                      size: 26,
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      );
    }

    return showMkSheet<String>(
      context,
      builder: (sheet) => StatefulBuilder(
        builder: (context, refresh) => MkSheetFrame(
          children: [
            const Center(child: MkIconDisc(Icons.badge_rounded, color: Mk.blue)),
            const SizedBox(height: 12),
            const Text(
              'Pièce d’identité',
              textAlign: TextAlign.center,
              style: TextStyle(color: menuBlue, fontSize: 21, height: 1.2, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 6),
            const Text(
              'Choisissez comment envoyer votre pièce d’identité.',
              textAlign: TextAlign.center,
              style: TextStyle(color: menuMuted, fontSize: 13.5, height: 1.4, fontWeight: FontWeight.w500),
            ),
            const SizedBox(height: 16),
            option(refresh, 'pdf', 'opt_pdf', 'Un seul fichier PDF (recto et verso)'),
            option(refresh, 'scan', 'opt_scan', 'Deux photos : recto puis verso'),
            const SizedBox(height: 6),
            MkBarButton(label: 'Continuer', filled: true, onTap: () => Navigator.pop(sheet, mode)),
          ],
        ),
      ),
    );
  }

  Future<void> _view(Map<String, dynamic> doc) async {
    final key = '${doc['key']}';
    setState(() => busy = '$key:view');
    await downloadVendorFile(
      context,
      '/mobile/v1/vendor/shop/documents/$key/file',
      key,
      done: 'Document enregistré.',
      label: screenText(doc['label'], 'Document'),
    );
    if (mounted) setState(() => busy = null);
  }

  @override
  Widget build(BuildContext context) {
    final docs = _docs.where(_visible).toList();
    final identity = docs.where((d) => d['group'] != 'company').toList();
    final company = docs.where((d) => d['group'] == 'company').toList();
    final status = '${data['kyc_status'] ?? ''}';
    final color = switch (status) {
      'verified' => menuGreen,
      'automated_checked' => menuAzure,
      'rejected' => menuRed,
      _ => Mk.orange,
    };
    final required = _docs.where((d) => d['required'] == true).toList();
    final sent = required.where((d) => d['uploaded'] == true).length;
    final pending = status != 'verified' && status != 'automated_checked' && status != 'rejected';

    return MenuPage(
      title: 'Documents de la boutique',
      subtitle: 'Vérification de votre identité et de votre entreprise',
      refresh: load,
      loading: loading,
      error: error,
      hasContent: data.isNotEmpty,
      children: [
        _HeroCard(
          icon: switch (status) {
            'verified' => Icons.how_to_reg_rounded,
            'rejected' => Icons.assignment_late_outlined,
            _ => Icons.folder_outlined,
          },
          color: status == 'verified' || status == 'rejected' ? color : menuAzure,
          title: 'Vérification des documents',
          art: 'assets/images/mockups/docs_hero.jpg',
          accent: status == 'rejected' || pending ? color : null,
          pill: switch (status) {
            'verified' => _status(screenText(data['kyc_label'], 'Vérifiés'), menuGreen, icon: Icons.check_circle_rounded),
            'rejected' => _status(screenText(data['kyc_label'], 'À renvoyer'), menuRed, icon: Icons.error_rounded),
            'automated_checked' => _status(screenText(data['kyc_label'], ''), menuAzure),
            _ => _status(screenText(data['kyc_label'], 'À compléter'), Mk.icon, icon: Icons.schedule_rounded, soft: true),
          },
          description: screenText(data['kyc_description'], ''),
          progress: required.isEmpty ? null : sent / required.length,
          progressColor: color,
          progressLabel: required.isEmpty ? null : '$sent/${required.length} obligatoires',
        ),
        if (identity.isNotEmpty) ...[menuCaption('IDENTITÉ DU VENDEUR'), for (final d in identity) _doc(d)],
        if (company.isNotEmpty) ...[menuCaption('DOCUMENTS DE L’ENTREPRISE'), for (final d in company) _doc(d)],
        Container(
          margin: const EdgeInsets.only(bottom: 10),
          padding: const EdgeInsets.fromLTRB(12, 11, 12, 11),
          decoration: BoxDecoration(color: const Color(0xFFE3EEFD), borderRadius: BorderRadius.circular(12)),
          child: Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: const BoxDecoration(color: Color(0xFFCFE0FB), shape: BoxShape.circle),
                child: const Icon(Icons.lock_outline_rounded, color: menuAzure, size: 20),
              ),
              const SizedBox(width: 10),
              const Expanded(
                child: Text.rich(
                  TextSpan(
                    children: [
                      TextSpan(text: 'Vos documents '),
                      TextSpan(
                        text: 'restent privés',
                        style: TextStyle(fontWeight: FontWeight.w800),
                      ),
                      TextSpan(text: ' : seule l’équipe de vérification OVANIE peut les consulter.'),
                    ],
                  ),
                  style: TextStyle(color: Mk.icon, fontSize: 12.5, height: 1.4, fontWeight: FontWeight.w500),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _doc(Map<String, dynamic> d) {
    final key = '${d['key']}';
    final uploaded = d['uploaded'] == true;
    final required = d['required'] == true;
    final missing = !uploaded && required;
    final optional = !uploaded && !required;
    final (IconData icon, Color tint) = switch (key) {
      'selfie' => (Icons.portrait_rounded, menuAzure),
      'tax' when d['file_type'] != 'pdf' => (Icons.upload_file_outlined, Mk.orange),
      _ when !uploaded => (Icons.upload_file_outlined, Mk.orange),
      _ when d['file_type'] == 'pdf' => (Icons.picture_as_pdf_outlined, menuRed),
      _ => (Icons.image_outlined, menuRed),
    };
    final pill = uploaded
        ? _status('Envoyé', menuGreen, icon: Icons.check_circle_rounded)
        : required
        ? _status('Obligatoire', _warn, icon: Icons.error_rounded)
        : _status('Facultatif', Mk.icon, icon: Icons.remove_circle_rounded, soft: true);

    final text = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Padding(
                padding: const EdgeInsets.only(top: 2),
                child: Text(
                  screenText(d['label'], ''),
                  style: const TextStyle(color: menuBlue, fontSize: 14.5, height: 1.25, fontWeight: FontWeight.w800),
                ),
              ),
            ),
            const SizedBox(width: 8),
            pill,
          ],
        ),
        const SizedBox(height: 4),
        Text(
          screenText(d['hint'], ''),
          style: const TextStyle(color: menuMuted, fontSize: 12.5, height: 1.4, fontWeight: FontWeight.w500),
        ),
        const SizedBox(height: 10),
        if (uploaded)
          Row(
            children: [
              Expanded(
                child: _button(
                  'Télécharger',
                  Icons.file_download_outlined,
                  busy == null ? () => _view(d) : null,
                  color: menuAzure,
                  text: Mk.blue,
                  loading: busy == '$key:view',
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _button(
                  'Remplacer',
                  Icons.sync_rounded,
                  busy == null ? () => _replace(d) : null,
                  loading: busy == key,
                ),
              ),
            ],
          )
        else if (missing)
          _button(
            'Ajouter',
            Icons.add_circle_outline_rounded,
            busy == null ? () => _replace(d) : null,
            filled: true,
            loading: busy == key,
            height: 42,
          )
        else
          FractionallySizedBox(
            widthFactor: .5,
            alignment: Alignment.centerLeft,
            child: _button(
              'Ajouter',
              Icons.add_rounded,
              busy == null ? () => _replace(d) : null,
              color: const Color(0xFFB8C2D6),
              text: menuBlue,
              loading: busy == key,
            ),
          ),
      ],
    );

    final content = Padding(
      padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
      child: optional
          ? text
          : Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _tile(icon, tint, size: 46),
                const SizedBox(width: 12),
                Expanded(child: text),
              ],
            ),
    );

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: missing
          ? CustomPaint(
              foregroundPainter: const _DashedBorder(Mk.orange, 14),
              child: Container(
                decoration: BoxDecoration(color: const Color(0xFFFFF6F0), borderRadius: BorderRadius.circular(14)),
                child: content,
              ),
            )
          : Container(
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
              child: content,
            ),
    );
  }
}

/// Contour en tirets d'un document obligatoire qui manque encore.
class _DashedBorder extends CustomPainter {
  const _DashedBorder(this.color, this.radius);
  final Color color;
  final double radius;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.3;
    final path = Path()..addRRect(RRect.fromRectAndRadius((Offset.zero & size).deflate(.65), Radius.circular(radius)));
    for (final metric in path.computeMetrics()) {
      for (var d = 0.0; d < metric.length; d += 9) {
        canvas.drawPath(metric.extractPath(d, d + 5), paint);
      }
    }
  }

  @override
  bool shouldRepaint(_DashedBorder old) => old.color != color || old.radius != radius;
}

// ============================================================================
// Sécurité du compte
// ============================================================================

/// Sécurité du compte : identifiants, mot de passe, alertes sur ce téléphone
/// et appareils connectés au compte vendeur.
class AccountSecurityScreen extends StatefulWidget {
  const AccountSecurityScreen({super.key});
  @override
  State<AccountSecurityScreen> createState() => _AccountSecurityState();
}

class _AccountSecurityState extends State<AccountSecurityScreen> {
  Map<String, dynamic> data = {};
  Map<String, dynamic> push = {};
  bool loading = true;
  String? busy;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      data = await menuApi('account/security');
      try {
        push = mapOf((await menuApi('push'))['data']);
      } catch (_) {
        push = {};
      }
    } catch (e) {
      error = ApiClient.friendlyError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _disconnect(Map<String, dynamic> session) async {
    final ok = await showMkConfirmSheet(
      context,
      icon: Icons.mobile_off_rounded,
      iconColor: menuRed,
      title: 'Déconnecter cet appareil ?',
      equalButtons: true,
      summary: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(color: const Color(0xFFF1F4F9), borderRadius: BorderRadius.circular(12)),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            _tile(
              screenText(session['name'], '').startsWith('Navigateur') ? Icons.laptop_mac_rounded : Icons.smartphone_rounded,
              Mk.blue,
              size: 38,
            ),
            const SizedBox(width: 12),
            Flexible(
              child: Text(
                screenText(session['name'], ''),
                style: const TextStyle(color: menuBlue, fontSize: 14.5, fontWeight: FontWeight.w800),
              ),
            ),
          ],
        ),
      ),
      message: 'L’appareil devra se reconnecter avec votre mot de passe pour accéder à votre espace vendeur.',
      confirmLabel: 'Déconnecter',
      confirmIcon: Icons.logout_rounded,
      confirmColor: menuRed,
      cancelColor: menuBlue,
    );
    if (!ok || !mounted) return;
    setState(() => busy = '${session['id']}');
    try {
      final r = await menuApi('account/sessions/${session['id']}', method: 'DELETE');
      await load();
      if (mounted) mkToast(context, screenText(r['message'], 'Appareil déconnecté.'));
    } catch (e) {
      if (mounted) menuError(context, e);
    } finally {
      if (mounted) setState(() => busy = null);
    }
  }

  Future<void> _enablePush() async {
    setState(() => busy = 'push');
    await PushNotificationService.instance.initialize(force: true);
    await load();
    if (mounted) setState(() => busy = null);
  }

  Widget _verified(bool ok) => ok
      ? _status('Vérifié', menuGreen, icon: Icons.check_circle_rounded)
      : _status('Non vérifié', Mk.icon, icon: Icons.info_rounded, soft: true);

  Widget _identifier(String label, String value, Widget? pill) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 9),
    child: Row(
      children: [
        SizedBox(
          width: 72,
          child: Text(
            label,
            style: const TextStyle(color: menuMuted, fontSize: 12.5, fontWeight: FontWeight.w500),
          ),
        ),
        Expanded(
          child: FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              value,
              style: const TextStyle(color: menuBlue, fontSize: 13, fontWeight: FontWeight.w600),
            ),
          ),
        ),
        if (pill != null) ...[const SizedBox(width: 8), pill],
      ],
    ),
  );

  @override
  Widget build(BuildContext context) {
    final sessions = rowsOf(data['sessions']);
    final others = sessions.where((s) => s['is_current'] != true).length;
    final service = PushNotificationService.instance;
    final pushOn = service.registered;
    final pushPossible = service.configuredInApp && push['available'] == true;
    final phone = menuPhone(data['phone']);

    return MenuPage(
      title: 'Sécurité du compte',
      subtitle: 'Mot de passe, appareils connectés et alertes',
      refresh: load,
      loading: loading,
      error: error,
      hasContent: data.isNotEmpty,
      children: [
        _HeroCard(
          icon: Icons.verified_user_rounded,
          color: menuGreen,
          title: 'Protégez votre boutique',
          art: 'assets/images/mockups/security_hero.jpg',
          description: others == 0
              ? 'Votre compte n’est ouvert que sur cet appareil.'
              : 'Votre compte est ouvert sur ${others + 1} appareils. Déconnectez ceux que vous ne reconnaissez pas.',
        ),
        _Block(
          icon: Icons.alternate_email_rounded,
          color: Mk.blue,
          title: 'Identifiants de connexion',
          child: Column(
            children: [
              _identifier('Email', screenText(data['email'], 'Non renseigné'), _verified(data['email_verified'] == true)),
              const Divider(height: 1, color: _line),
              _identifier(
                'Téléphone',
                phone.isEmpty ? 'Non renseigné' : phone,
                data['phone_verified'] == true ? _verified(true) : null,
              ),
            ],
          ),
        ),
        _Block(
          icon: Icons.lock_outline_rounded,
          color: Mk.blue,
          title: 'Mot de passe',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'Le même mot de passe sert sur l’application et sur l’espace vendeur Web. Changez-le si vous pensez qu’il a été vu par quelqu’un.',
                style: TextStyle(color: menuMuted, fontSize: 12.5, height: 1.45, fontWeight: FontWeight.w500),
              ),
              const SizedBox(height: 12),
              _button(
                'Modifier le mot de passe',
                Icons.key_outlined,
                () async {
                  await Navigator.of(
                    context,
                  ).push(MaterialPageRoute<void>(builder: (_) => const ChangePasswordScreen()));
                  if (mounted) load();
                },
                color: menuBlue,
                height: 46,
                chevron: true,
              ),
            ],
          ),
        ),
        _Block(
          icon: Icons.notifications_none_rounded,
          color: Mk.orange,
          title: 'Alertes sur ce téléphone',
          trailing: pushOn
              ? _status('Activées', menuGreen, icon: Icons.check_circle_rounded)
              : _status('Désactivées', Mk.icon, icon: pushPossible ? null : Icons.info_rounded, soft: true),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                pushOn
                    ? 'Vous êtes prévenu ici dès qu’une commande, un retour, un avis ou un versement arrive, même application fermée.'
                    : pushPossible
                    ? 'Activez les alertes pour être prévenu dès qu’une commande arrive, même application fermée.'
                    : 'Les alertes instantanées ne sont pas disponibles sur cette installation. Vos alertes restent dans l’onglet Notifications.',
                style: const TextStyle(color: menuMuted, fontSize: 12.5, height: 1.45, fontWeight: FontWeight.w500),
              ),
              if (!pushOn && pushPossible) ...[
                const SizedBox(height: 12),
                _button(
                  'Activer les alertes',
                  Icons.notifications_none_rounded,
                  busy == null ? _enablePush : null,
                  filled: true,
                  loading: busy == 'push',
                  height: 46,
                ),
              ],
            ],
          ),
        ),
        menuCaption('APPAREILS CONNECTÉS'),
        if (sessions.isEmpty)
          menuEmpty('Aucun appareil', icon: Icons.devices_other_rounded)
        else
          for (final s in sessions) _session(s),
      ],
    );
  }

  String _day(dynamic raw) {
    final date = DateTime.tryParse('${raw ?? ''}')?.toLocal();
    if (date == null) return '';
    String two(int v) => v.toString().padLeft(2, '0');
    return '${two(date.day)}/${two(date.month)}/${date.year}';
  }

  Widget _session(Map<String, dynamic> s) {
    final current = s['is_current'] == true;
    final name = screenText(s['name'], 'Appareil');
    final browser = name.startsWith('Navigateur');
    final last = _day(s['last_used_at']);
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.fromLTRB(12, 10, 8, 10),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
      child: Row(
        children: [
          _tile(
            browser ? Icons.laptop_mac_rounded : Icons.smartphone_rounded,
            current ? menuGreen : Mk.blue,
            size: 40,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Text(
                    name,
                    style: const TextStyle(color: menuBlue, fontSize: 13.5, height: 1.25, fontWeight: FontWeight.w800),
                  ),
                ),
                if (!current && last.isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text(
                    'Dernière activité : $last',
                    style: const TextStyle(color: menuMuted, fontSize: 12, fontWeight: FontWeight.w500),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(width: 6),
          if (current)
            Padding(
              padding: const EdgeInsets.only(right: 4),
              child: _status('Cet appareil', menuGreen, icon: Icons.check_circle_rounded),
            )
          else if (busy == '${s['id']}')
            const Padding(
              padding: EdgeInsets.all(12),
              child: SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2, color: menuRed)),
            )
          else
            Semantics(
              button: true,
              child: InkWell(
                borderRadius: BorderRadius.circular(8),
                onTap: busy == null ? () => _disconnect(s) : null,
                child: const Padding(
                  padding: EdgeInsets.symmetric(horizontal: 8, vertical: 12),
                  child: Text(
                    'Déconnecter',
                    style: TextStyle(color: menuRed, fontSize: 12.5, fontWeight: FontWeight.w800),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

// ============================================================================
// Modifier le mot de passe
// ============================================================================

class ChangePasswordScreen extends StatefulWidget {
  const ChangePasswordScreen({super.key});
  @override
  State<ChangePasswordScreen> createState() => _ChangePasswordState();
}

class _ChangePasswordState extends State<ChangePasswordScreen> {
  final _current = TextEditingController();
  final _password = TextEditingController();
  final _confirmation = TextEditingController();
  bool _hideCurrent = true, _hidePassword = true, _hideConfirmation = true;
  bool _logoutOthers = true, _saving = false;
  String? _error, _currentError, _passwordError, _confirmationError;

  @override
  void dispose() {
    _current.dispose();
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  bool get _longEnough => _password.text.length >= 8;
  bool get _hasLetter => RegExp(r'[A-Za-zÀ-ÿ]').hasMatch(_password.text);
  bool get _hasDigit => RegExp(r'\d').hasMatch(_password.text);

  bool _validate() {
    _currentError = _current.text.isEmpty ? 'Saisissez votre mot de passe actuel.' : null;
    _passwordError = !_longEnough || !_hasLetter || !_hasDigit
        ? 'Le mot de passe ne respecte pas les règles ci-dessous.'
        : (_password.text == _current.text ? 'Choisissez un mot de passe différent de l’actuel.' : null);
    _confirmationError = _confirmation.text != _password.text ? 'Les deux mots de passe ne sont pas identiques.' : null;
    return _currentError == null && _passwordError == null && _confirmationError == null;
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    setState(() => _error = null);
    final valid = _validate();
    setState(() {});
    if (!valid) return;
    setState(() => _saving = true);
    try {
      final r = await menuApi(
        'account/password',
        method: 'PUT',
        data: {
          'current_password': _current.text,
          'password': _password.text,
          'password_confirmation': _confirmation.text,
          'logout_others': _logoutOthers,
        },
      );
      if (!mounted) return;
      await _done(number(r['revoked_sessions']).toInt());
      if (mounted) Navigator.of(context).pop(true);
    } catch (e) {
      final message = ApiClient.friendlyError(e);
      if (mounted) {
        setState(() {
          if (message.contains('actuel')) {
            _currentError = message;
          } else {
            _error = message;
          }
        });
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _done(int revoked) => showMkDialog<void>(
    context,
    dismissible: false,
    header: Padding(
      padding: const EdgeInsets.only(bottom: 44),
      child: Stack(
        clipBehavior: Clip.none,
        alignment: Alignment.bottomCenter,
        children: [
          ShaderMask(
            blendMode: BlendMode.dstIn,
            shaderCallback: (rect) => const LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [Colors.white, Colors.white, Colors.transparent],
              stops: [0, .7, 1],
            ).createShader(rect),
            child: Image.asset(
              'assets/images/mockups/reset_done.jpg',
              height: 190,
              width: double.infinity,
              fit: BoxFit.cover,
              excludeFromSemantics: true,
            ),
          ),
          Positioned(
            bottom: -34,
            child: Container(
              padding: const EdgeInsets.all(5),
              decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
              child: const MkIconDisc(Icons.check_rounded, color: Color(0xFF16A34A), size: 68),
            ),
          ),
        ],
      ),
    ),
    title: 'Mot de passe modifié',
    message: revoked > 0
        ? 'Votre nouveau mot de passe est actif. $revoked autre${revoked > 1 ? 's' : ''} appareil${revoked > 1 ? 's ont' : ' a'} été déconnecté${revoked > 1 ? 's' : ''}.'
        : 'Votre nouveau mot de passe est actif sur l’application et sur l’espace vendeur Web.',
    actions: const [MkDialogAction('Terminé', null)],
  );

  Widget _rule(String label, bool ok) => Padding(
    padding: const EdgeInsets.only(top: 8),
    child: Row(
      children: [
        Icon(
          ok ? Icons.check_circle_rounded : Icons.radio_button_unchecked_rounded,
          color: ok ? menuGreen : const Color(0xFFB8C2D6),
          size: 20,
        ),
        const SizedBox(width: 8),
        Text(
          label,
          style: TextStyle(color: ok ? menuGreen : menuMuted, fontSize: 12.5, fontWeight: FontWeight.w500),
        ),
      ],
    ),
  );

  /// Champ mot de passe ; en erreur, fond rosé, contour rouge et message dessous.
  Widget _field({
    required String label,
    required TextEditingController controller,
    required String hint,
    required IconData icon,
    required bool hidden,
    required VoidCallback toggle,
    required String? error,
    required VoidCallback clearError,
    TextInputAction action = TextInputAction.next,
    Iterable<String> autofill = const [AutofillHints.newPassword],
  }) {
    var decoration = mkInput(
      hint: hint,
      icon: icon,
      suffix: MkEye(hidden: hidden, onTap: toggle),
    );
    if (error != null) {
      OutlineInputBorder red(double width) => OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(color: menuRed, width: width),
      );
      decoration = decoration.copyWith(
        fillColor: const Color(0xFFFFF1F1),
        enabledBorder: red(1.3),
        focusedBorder: red(1.8),
        disabledBorder: red(1.3),
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        MkLabel(label),
        TextField(
          controller: controller,
          obscureText: hidden,
          enabled: !_saving,
          style: mkFieldText,
          textInputAction: action,
          autofillHints: autofill,
          onSubmitted: action == TextInputAction.done ? (_) => _submit() : null,
          onChanged: (_) => setState(clearError),
          decoration: decoration,
        ),
        if (error != null)
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(Icons.error_rounded, color: menuRed, size: 20),
                const SizedBox(width: 8),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.only(top: 1),
                    child: Text(
                      error,
                      style: const TextStyle(color: menuRed, fontSize: 12.5, height: 1.35, fontWeight: FontWeight.w500),
                    ),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return MenuPage(
      title: 'Modifier le mot de passe',
      subtitle: 'Choisissez un mot de passe que vous n’utilisez nulle part ailleurs',
      refresh: () async {},
      hasContent: true,
      showTabs: false,
      actions: [
        Semantics(
          button: true,
          label: _saving ? 'Enregistrement en cours' : 'Enregistrer',
          excludeSemantics: true,
          child: Material(
            color: Mk.orange,
            borderRadius: BorderRadius.circular(10),
            clipBehavior: Clip.antiAlias,
            child: InkWell(
              onTap: _saving ? null : _submit,
              child: SizedBox(
                height: 50,
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    if (_saving)
                      const SizedBox.square(
                        dimension: 20,
                        child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white),
                      )
                    else
                      const Icon(Icons.check_rounded, color: Colors.white, size: 24),
                    const SizedBox(width: 10),
                    Text(
                      _saving ? 'Enregistrement…' : 'Enregistrer',
                      style: const TextStyle(color: Colors.white, fontSize: 15.5, fontWeight: FontWeight.w700),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ],
      children: [
        if (_error != null) ...[MkNotice(_error!), const SizedBox(height: 10)],
        Container(
          margin: const EdgeInsets.only(bottom: 10),
          padding: const EdgeInsets.fromLTRB(14, 14, 14, 16),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _field(
                label: 'Mot de passe actuel',
                controller: _current,
                hint: 'Votre mot de passe actuel',
                icon: Icons.lock_outline_rounded,
                hidden: _hideCurrent,
                toggle: () => setState(() => _hideCurrent = !_hideCurrent),
                error: _currentError,
                clearError: () => _currentError = null,
                autofill: const [AutofillHints.password],
              ),
              const SizedBox(height: 16),
              _field(
                label: 'Nouveau mot de passe',
                controller: _password,
                hint: 'Au moins 8 caractères',
                icon: Icons.key_outlined,
                hidden: _hidePassword,
                toggle: () => setState(() => _hidePassword = !_hidePassword),
                error: _passwordError,
                clearError: () => _passwordError = null,
              ),
              const SizedBox(height: 2),
              _rule('Au moins 8 caractères', _longEnough),
              _rule('Au moins une lettre', _hasLetter),
              _rule('Au moins un chiffre', _hasDigit),
              const SizedBox(height: 16),
              _field(
                label: 'Confirmer le nouveau mot de passe',
                controller: _confirmation,
                hint: 'Saisissez-le une seconde fois',
                icon: Icons.key_outlined,
                hidden: _hideConfirmation,
                toggle: () => setState(() => _hideConfirmation = !_hideConfirmation),
                error: _confirmationError,
                clearError: () => _confirmationError = null,
                action: TextInputAction.done,
              ),
            ],
          ),
        ),
        Container(
          margin: const EdgeInsets.only(bottom: 10),
          padding: const EdgeInsets.fromLTRB(14, 14, 8, 14),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
          child: Row(
            children: [
              menuIcon(Icons.devices_rounded, color: menuAzure, size: 40),
              const SizedBox(width: 12),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    FittedBox(
                      fit: BoxFit.scaleDown,
                      alignment: Alignment.centerLeft,
                      child: Text(
                        'Déconnecter les autres appareils',
                        style: TextStyle(color: menuBlue, fontSize: 14, fontWeight: FontWeight.w800),
                      ),
                    ),
                    SizedBox(height: 3),
                    Text(
                      'Recommandé si vous pensez que quelqu’un connaît votre mot de passe.',
                      style: TextStyle(color: menuMuted, fontSize: 12.5, height: 1.35, fontWeight: FontWeight.w500),
                    ),
                  ],
                ),
              ),
              Switch(
                value: _logoutOthers,
                activeThumbColor: Colors.white,
                activeTrackColor: Mk.orange,
                onChanged: _saving ? null : (v) => setState(() => _logoutOthers = v),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
