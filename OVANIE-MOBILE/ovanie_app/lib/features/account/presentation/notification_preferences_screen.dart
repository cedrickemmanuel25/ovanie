import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../payments/presentation/payment_ui.dart';
import '../data/client_account_repository.dart';
import '../domain/account_models.dart';

const _navy = Color(0xFF0A1B3D);
const _muted = Color(0xFF6B7890);
const _line = Color(0xFFE7ECF2);
const _switchBlue = Color(0xFF1463E0);

/// Canaux présentés au client, dans l'ordre des maquettes. Le canal
/// « dans l'application » reste géré par OVANIE et n'est pas modifié ici.
const _visibleChannels = ['email', 'sms', 'push'];

class _TypeStyle {
  final IconData icon;
  final Color color;
  final String description;

  const _TypeStyle(this.icon, this.color, this.description);
}

const _typeOrder = ['orders', 'payments', 'deliveries', 'returns', 'promotions', 'support', 'newsletter', 'cart'];

_TypeStyle _styleFor(String key) => switch (key) {
  'orders' => const _TypeStyle(
    Icons.inventory_2_outlined,
    Color(0xFF1463E0),
    'Notifications sur vos commandes et leur statut.',
  ),
  'payments' => const _TypeStyle(
    Icons.payment_outlined,
    Color(0xFF138A4B),
    'Notifications sur vos paiements et remboursements.',
  ),
  'deliveries' => const _TypeStyle(
    Icons.local_shipping_outlined,
    OvanieColors.orange,
    'Notifications sur l’expédition et la livraison de vos commandes.',
  ),
  'returns' => const _TypeStyle(
    Icons.assignment_return_outlined,
    Color(0xFF7A4CE0),
    'Suivi de vos retours, réclamations et remboursements.',
  ),
  'promotions' => const _TypeStyle(
    Icons.local_offer_outlined,
    Color(0xFFD32F2F),
    'Offres spéciales, réductions et nouveautés.',
  ),
  'support' => const _TypeStyle(Icons.headset_mic_outlined, Color(0xFF0E8A9A), 'Réponses à vos demandes d’assistance.'),
  'newsletter' => const _TypeStyle(Icons.campaign_outlined, Color(0xFFB7791F), 'Actualités et informations OVANIE.'),
  'cart' => const _TypeStyle(
    Icons.shopping_cart_outlined,
    Color(0xFF3949AB),
    'Rappels sur les articles laissés dans votre panier.',
  ),
  'security' => const _TypeStyle(
    Icons.lock_outline_rounded,
    Color(0xFF6B7280),
    'Alertes de sécurité et activités importantes sur votre compte.',
  ),
  _ => const _TypeStyle(
    Icons.notifications_none_rounded,
    Color(0xFF4A586E),
    'Informations importantes sur votre compte et vos demandes.',
  ),
};

IconData _channelIcon(String key) => switch (key) {
  'email' => Icons.mail_outline_rounded,
  'sms' => Icons.chat_bubble_outline_rounded,
  _ => Icons.smartphone_rounded,
};

class NotificationPreferencesScreen extends StatefulWidget {
  const NotificationPreferencesScreen({super.key});

  @override
  State<NotificationPreferencesScreen> createState() => _NotificationPreferencesScreenState();
}

class _NotificationPreferencesScreenState extends State<NotificationPreferencesScreen> {
  final _repository = const ClientAccountRepository();
  NotificationPreferencesData? _data;
  Map<String, Map<String, bool>> _prefs = {};
  bool _loading = true;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _apply(NotificationPreferencesData data) {
    _data = data;
    _prefs = {for (final entry in data.preferences.entries) entry.key: Map<String, bool>.from(entry.value)};
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await _repository.notificationPreferences();
      if (!mounted) return;
      setState(() {
        _apply(data);
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = ApiClient.friendlyError(error);
      });
    }
  }

  Future<void> _save() async {
    if (_saving) return;
    setState(() => _saving = true);
    try {
      final updated = await _repository.updateNotificationPreferences(_prefs);
      if (!mounted) return;
      setState(() => _apply(updated));
      showOvanieToast(context, 'Préférences enregistrées.');
    } catch (error) {
      if (mounted) showOvanieToast(context, ApiClient.friendlyError(error), error: true);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  List<NotificationTypeOption> _sortedTypes(List<NotificationTypeOption> types) {
    int rank(String key) {
      if (key == 'security') return 1000;
      final index = _typeOrder.indexOf(key);
      return index < 0 ? 500 : index;
    }

    return [...types]..sort((a, b) => rank(a.key).compareTo(rank(b.key)));
  }

  @override
  Widget build(BuildContext context) {
    final data = _data;
    return Scaffold(
      backgroundColor: const Color(0xFFF6F8FB),
      appBar: AppBar(
        backgroundColor: const Color(0xFFF6F8FB),
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        toolbarHeight: 84,
        titleSpacing: 0,
        leading: IconButton(
          onPressed: () => Navigator.maybePop(context),
          tooltip: 'Retour',
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: _navy, size: 22),
        ),
        title: const Padding(
          padding: EdgeInsets.only(right: 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Notifications & préférences',
                style: TextStyle(color: _navy, fontSize: 20, fontWeight: FontWeight.w800),
              ),
              SizedBox(height: 3),
              Text(
                'Choisissez comment OVANIE vous informe de vos commandes, paiements et livraisons.',
                maxLines: 2,
                style: TextStyle(color: _muted, fontSize: 13, height: 1.35),
              ),
            ],
          ),
        ),
      ),
      bottomNavigationBar: data == null
          ? null
          : SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(14, 8, 14, 10),
                child: SizedBox(
                  height: 50,
                  child: FilledButton(
                    onPressed: _saving ? () {} : _save,
                    style: FilledButton.styleFrom(
                      backgroundColor: OvanieColors.orange,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        if (_saving) ...[
                          const SizedBox.square(
                            dimension: 18,
                            child: CircularProgressIndicator(strokeWidth: 2.2, color: Colors.white),
                          ),
                          const SizedBox(width: 12),
                        ],
                        Text(
                          _saving ? 'Enregistrement…' : 'Enregistrer',
                          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: OvanieColors.orange))
          : data == null
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(28),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.cloud_off_rounded, color: _muted, size: 52),
                    const SizedBox(height: 14),
                    Text(
                      _error ?? 'Préférences indisponibles.',
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: _muted, height: 1.45),
                    ),
                    const SizedBox(height: 18),
                    PaymentButton(label: 'Réessayer', onPressed: _load),
                  ],
                ),
              ),
            )
          : ListView(
              padding: const EdgeInsets.fromLTRB(12, 2, 12, 16),
              children: [
                for (final type in _sortedTypes(data.types))
                  _PreferenceCard(
                    type: type,
                    channels: [
                      for (final key in _visibleChannels) ...data.channels.where((channel) => channel.key == key),
                    ],
                    values: _prefs[type.key] ?? const {},
                    onChanged: (channel, value) {
                      if (type.key == 'security') return;
                      setState(() => _prefs.putIfAbsent(type.key, () => <String, bool>{})[channel] = value);
                    },
                  ),
              ],
            ),
    );
  }
}

class _PreferenceCard extends StatelessWidget {
  const _PreferenceCard({required this.type, required this.channels, required this.values, required this.onChanged});

  final NotificationTypeOption type;
  final List<NotificationChannelOption> channels;
  final Map<String, bool> values;
  final void Function(String channel, bool value) onChanged;

  @override
  Widget build(BuildContext context) {
    final style = _styleFor(type.key);
    final locked = type.key == 'security';
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.fromLTRB(10, 10, 10, 2),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(color: style.color, shape: BoxShape.circle),
                child: Icon(style.icon, color: Colors.white, size: 23),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      locked ? 'Sécurité du compte' : type.label,
                      style: const TextStyle(color: _navy, fontSize: 16.5, fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 2),
                    Text(style.description, style: const TextStyle(color: _muted, fontSize: 12.5, height: 1.3)),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          for (final channel in channels) ...[
            const Divider(height: 1, color: _line),
            _ChannelRow(
              channel: channel,
              locked: locked,
              value: locked ? true : (channel.available && (values[channel.key] ?? false)),
              onChanged: (value) => onChanged(channel.key, value),
            ),
          ],
        ],
      ),
    );
  }
}

class _ChannelRow extends StatelessWidget {
  const _ChannelRow({required this.channel, required this.locked, required this.value, required this.onChanged});

  final NotificationChannelOption channel;
  final bool locked;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    final unavailable = !channel.available && !locked;
    final row = Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
      child: Row(
        children: [
          Icon(
            _channelIcon(channel.key),
            color: unavailable ? const Color(0xFF9AA5B5) : const Color(0xFF2E3A4D),
            size: 21,
          ),
          const SizedBox(width: 14),
          Expanded(
            flex: locked ? 6 : 10,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  channel.label,
                  style: TextStyle(color: unavailable ? const Color(0xFF7D889A) : _navy, fontSize: 14.5),
                ),
                if (unavailable)
                  const Text('Non configuré dans OVANIE', style: TextStyle(color: Color(0xFF8B96A8), fontSize: 12)),
              ],
            ),
          ),
          if (locked)
            const Expanded(
              flex: 8,
              child: Row(
                children: [
                  Icon(Icons.lock_outline_rounded, color: Color(0xFF7D889A), size: 15),
                  SizedBox(width: 5),
                  Expanded(
                    child: Text(
                      'Toujours activé pour protéger votre compte.',
                      style: TextStyle(color: Color(0xFF7D889A), fontSize: 11.5, height: 1.25),
                    ),
                  ),
                ],
              ),
            ),
          Switch(
            value: value,
            onChanged: locked || unavailable ? null : onChanged,
            activeThumbColor: Colors.white,
            activeTrackColor: locked ? const Color(0xFFC9D0DA) : _switchBlue,
            inactiveThumbColor: Colors.white,
            inactiveTrackColor: const Color(0xFFDDE2E9),
            trackOutlineColor: const WidgetStatePropertyAll(Colors.transparent),
          ),
        ],
      ),
    );
    if (!unavailable) return row;
    return Container(
      margin: const EdgeInsets.symmetric(vertical: 3),
      decoration: BoxDecoration(color: const Color(0xFFF2F4F7), borderRadius: BorderRadius.circular(8)),
      child: row,
    );
  }
}
