import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../data/client_account_repository.dart';
import '../domain/account_models.dart';

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

  Future<void> _load() async {
    try {
      final data = await _repository.notificationPreferences();
      if (!mounted) return;
      setState(() {
        _data = data;
        _prefs = {
          for (final entry in data.preferences.entries)
            entry.key: Map<String, bool>.from(entry.value),
        };
        _loading = false;
        _error = null;
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
      setState(() {
        _data = updated;
        _prefs = {
          for (final entry in updated.preferences.entries)
            entry.key: Map<String, bool>.from(entry.value),
        };
      });
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Préférences enregistrées.')));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.friendlyError(error))));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final data = _data;
    return Scaffold(
      appBar: AppBar(title: const Text('Notifications & préférences')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Padding(padding: const EdgeInsets.all(24), child: Text(_error!, textAlign: TextAlign.center)))
              : data == null
                  ? const SizedBox.shrink()
                  : ListView(
                      padding: const EdgeInsets.all(16),
                      children: [
                        Container(
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            border: Border.all(color: OvanieColors.border),
                            borderRadius: BorderRadius.circular(16),
                          ),
                          child: Text(
                            data.channels.any((channel) => channel.key == 'push' && channel.available)
                                ? 'Les canaux affichés reflètent la configuration Laravel. Firebase Cloud Messaging est actuellement déclaré opérationnel par OVANIE.'
                                : 'Les canaux affichés reflètent la configuration Laravel. Le push mobile reste indisponible tant que Firebase Cloud Messaging n’est pas réellement configuré.',
                            style: const TextStyle(color: OvanieColors.muted, height: 1.45),
                          ),
                        ),
                        const SizedBox(height: 14),
                        ...data.types.map((type) => _PreferenceCard(
                              type: type,
                              channels: data.channels,
                              values: _prefs[type.key] ?? const {},
                              onChanged: (channel, value) {
                                if (type.key == 'security') return;
                                setState(() {
                                  _prefs.putIfAbsent(type.key, () => <String, bool>{})[channel] = value;
                                });
                              },
                            )),
                        const SizedBox(height: 12),
                        FilledButton.icon(
                          onPressed: _saving ? null : _save,
                          style: FilledButton.styleFrom(backgroundColor: OvanieColors.orange, minimumSize: const Size.fromHeight(48)),
                          icon: const Icon(Icons.save_outlined),
                          label: Text(_saving ? 'Enregistrement…' : 'Enregistrer'),
                        ),
                      ],
                    ),
    );
  }
}

class _PreferenceCard extends StatelessWidget {
  final NotificationTypeOption type;
  final List<NotificationChannelOption> channels;
  final Map<String, bool> values;
  final void Function(String channel, bool value) onChanged;
  const _PreferenceCard({required this.type, required this.channels, required this.values, required this.onChanged});

  @override
  Widget build(BuildContext context) => Card(
        margin: const EdgeInsets.only(bottom: 10),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(14, 13, 14, 8),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(type.label, style: const TextStyle(fontWeight: FontWeight.w900)),
            if (type.key == 'security')
              const Padding(
                padding: EdgeInsets.only(top: 3),
                child: Text('Toujours activé pour protéger votre compte.', style: TextStyle(fontSize: 11, color: OvanieColors.muted)),
              ),
            const SizedBox(height: 5),
            ...channels.map((channel) {
              final enabled = channel.available && type.key != 'security';
              return SwitchListTile(
                contentPadding: EdgeInsets.zero,
                dense: true,
                title: Text(channel.label),
                subtitle: channel.available ? null : const Text('Non configuré dans OVANIE'),
                value: channel.available ? (values[channel.key] ?? false) : false,
                onChanged: enabled ? (value) => onChanged(channel.key, value) : null,
              );
            }),
          ]),
        ),
      );
}
