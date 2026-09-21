import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../data/client_account_repository.dart';
import '../domain/account_models.dart';

class LoyaltyScreen extends StatefulWidget {
  const LoyaltyScreen({super.key});
  @override
  State<LoyaltyScreen> createState() => _LoyaltyScreenState();
}

class _LoyaltyScreenState extends State<LoyaltyScreen> {
  final _repository = const ClientAccountRepository();
  LoyaltyData? _data;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final data = await _repository.loyalty();
      if (!mounted) return;
      setState(() {
        _data = data;
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

  String _money(num value) {
    final raw = value.round().toString();
    final out = StringBuffer();
    for (var i = 0; i < raw.length; i++) {
      if (i > 0 && (raw.length - i) % 3 == 0) out.write(' ');
      out.write(raw[i]);
    }
    return '${out.toString()} FCFA';
  }

  @override
  Widget build(BuildContext context) {
    final data = _data;
    return Scaffold(
      appBar: AppBar(title: const Text('Points & avantages OVANIE')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Padding(padding: const EdgeInsets.all(24), child: Text(_error!, textAlign: TextAlign.center)))
              : data == null
                  ? const SizedBox.shrink()
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView(
                        padding: const EdgeInsets.all(16),
                        children: [
                          Container(
                            padding: const EdgeInsets.all(18),
                            decoration: BoxDecoration(color: OvanieColors.navy, borderRadius: BorderRadius.circular(18)),
                            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              const Text('Solde fidélité réel', style: TextStyle(color: Colors.white70)),
                              const SizedBox(height: 6),
                              Text('${data.points} points', style: const TextStyle(color: Colors.white, fontSize: 27, fontWeight: FontWeight.w900)),
                              const SizedBox(height: 10),
                              Text('Valeur disponible : ${_money(data.availableDiscountXof)}', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
                              if (data.debt > 0)
                                Text('Dette de points : ${data.debt}', style: const TextStyle(color: Color(0xFFFFD7BD))),
                            ]),
                          ),
                          const SizedBox(height: 12),
                          const Text(
                            'OVANIE ne dispose pas actuellement d’un véritable module « carte cadeau » distinct. Cette page affiche uniquement le programme fidélité réellement présent dans Laravel.',
                            style: TextStyle(color: OvanieColors.muted, height: 1.45),
                          ),
                          const SizedBox(height: 22),
                          const Text('Historique', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w900)),
                          const SizedBox(height: 10),
                          if (data.transactions.isEmpty)
                            const Text('Aucun mouvement de points pour le moment.')
                          else
                            ...data.transactions.map((tx) => Card(
                                  margin: const EdgeInsets.only(bottom: 8),
                                  child: ListTile(
                                    leading: CircleAvatar(
                                      child: Icon(tx.points >= 0 ? Icons.add_rounded : Icons.remove_rounded),
                                    ),
                                    title: Text(tx.description.isEmpty ? tx.type : tx.description, style: const TextStyle(fontWeight: FontWeight.w800)),
                                    subtitle: Text(tx.reference),
                                    trailing: Text(
                                      '${tx.points >= 0 ? '+' : ''}${tx.points}',
                                      style: TextStyle(fontWeight: FontWeight.w900, color: tx.points >= 0 ? OvanieColors.success : OvanieColors.danger),
                                    ),
                                  ),
                                )),
                        ],
                      ),
                    ),
    );
  }
}
