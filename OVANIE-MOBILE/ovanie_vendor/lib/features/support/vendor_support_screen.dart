import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import '../menu/menu_ui.dart';

class VendorSupportScreen extends StatefulWidget {
  const VendorSupportScreen({
    super.key,
    this.initialCategory,
    this.initialContextType,
    this.initialContextId,
    this.initialSubject,
    this.startNew = false,
  });

  final String? initialCategory;
  final String? initialContextType;
  final int? initialContextId;
  final String? initialSubject;
  final bool startNew;

  @override
  State<VendorSupportScreen> createState() => _VendorSupportScreenState();
}

class _VendorSupportScreenState extends State<VendorSupportScreen> {
  Map<String, dynamic> _data = const {};
  bool _loading = true;
  String? _error;
  bool _openedInitialComposer = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (mounted) setState(() { _loading = true; _error = null; });
    try {
      final response = await menuApi('support');
      if (!mounted) return;
      setState(() => _data = mapOf(response['data']));
      if (widget.startNew && !_openedInitialComposer) {
        _openedInitialComposer = true;
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (mounted) _compose();
        });
      }
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<Map<String, dynamic>> get _tickets => rowsOf(_data['tickets']);

  Future<void> _compose() async {
    final category = ValueNotifier<String>(widget.initialCategory ?? 'general');
    final subject = TextEditingController(text: widget.initialSubject ?? '');
    final description = TextEditingController();
    var sending = false;

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) => SafeArea(
          top: false,
          child: Padding(
            padding: EdgeInsets.fromLTRB(18, 14, 18, MediaQuery.viewInsetsOf(context).bottom + 20),
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Center(child: SizedBox(width: 42, child: Divider(thickness: 4, color: Color(0xFFD9E1EE)))),
                  const SizedBox(height: 8),
                  const Text('Nouvelle demande', style: TextStyle(color: menuBlue, fontSize: 21, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 4),
                  const Text('Votre demande sera liée à votre compte vendeur et à votre boutique.', style: TextStyle(color: menuMuted, height: 1.35)),
                  if (widget.initialContextType != null && widget.initialContextId != null) ...[
                    const SizedBox(height: 12),
                    _ContextBadge(label: 'Contexte automatiquement associé : ${_contextLabel(widget.initialContextType!)}'),
                  ],
                  const SizedBox(height: 16),
                  ValueListenableBuilder<String>(
                    valueListenable: category,
                    builder: (_, value, __) => DropdownButtonFormField<String>(
                      initialValue: value,
                      decoration: _input('Catégorie'),
                      items: const [
                        DropdownMenuItem(value: 'general', child: Text('Général')),
                        DropdownMenuItem(value: 'shop', child: Text('Boutique')),
                        DropdownMenuItem(value: 'products', child: Text('Produit')),
                        DropdownMenuItem(value: 'orders', child: Text('Commande')),
                        DropdownMenuItem(value: 'payouts', child: Text('Versement vendeur')),
                        DropdownMenuItem(value: 'delivery', child: Text('Livraison')),
                        DropdownMenuItem(value: 'technical', child: Text('Problème technique')),
                      ],
                      onChanged: sending ? null : (v) { if (v != null) category.value = v; },
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(controller: subject, decoration: _input('Objet')),
                  const SizedBox(height: 12),
                  TextField(controller: description, minLines: 5, maxLines: 9, decoration: _input('Décrivez précisément votre demande')),
                  const SizedBox(height: 18),
                  SizedBox(
                    width: double.infinity,
                    height: 50,
                    child: FilledButton.icon(
                      onPressed: sending ? null : () async {
                        if (subject.text.trim().length < 4 || description.text.trim().length < 10) {
                          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Renseignez un objet et une description suffisamment détaillée.')));
                          return;
                        }
                        setSheetState(() => sending = true);
                        try {
                          final contexts = <Map<String, dynamic>>[];
                          if (widget.initialContextType != null && widget.initialContextId != null) {
                            contexts.add({'type': widget.initialContextType, 'id': widget.initialContextId});
                          }
                          await menuApi('support/tickets', method: 'POST', data: {
                            'category': category.value,
                            'subject': subject.text.trim(),
                            'description': description.text.trim(),
                            if (contexts.isNotEmpty) 'contexts': contexts,
                          });
                          if (!context.mounted) return;
                          Navigator.pop(context);
                          ScaffoldMessenger.of(this.context).showSnackBar(const SnackBar(content: Text('Votre demande a été envoyée au Support OVANIE.')));
                          await _load();
                        } catch (error) {
                          if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.friendlyError(error))));
                        } finally {
                          if (context.mounted) setSheetState(() => sending = false);
                        }
                      },
                      style: FilledButton.styleFrom(backgroundColor: menuOrange, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
                      icon: sending ? const SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.send_outlined),
                      label: const Text('Envoyer au support', style: TextStyle(fontWeight: FontWeight.w900)),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
    category.dispose();
    subject.dispose();
    description.dispose();
  }

  Future<void> _openTicket(int id) async {
    try {
      final response = await menuApi('support/tickets/$id');
      final detail = mapOf(response['data']);
      if (!mounted) return;
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        backgroundColor: Colors.white,
        shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
        builder: (_) => _VendorTicketSheet(ticket: detail, onReply: (message) async {
          await menuApi('support/tickets/$id/messages', method: 'POST', data: {'message': message});
        }),
      );
      await _load();
    } catch (error) {
      if (mounted) menuError(context, error);
    }
  }

  @override
  Widget build(BuildContext context) {
    final requester = mapOf(_data['requester']);
    final open = _tickets.where((e) => !['resolved', 'closed', 'cancelled'].contains('${e['status']}')).length;
    return MenuPage(
      title: 'Centre d’assistance',
      subtitle: 'Vos demandes vendeur, au même endroit',
      refresh: _load,
      loading: _loading,
      error: _error,
      children: [
        Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            gradient: const LinearGradient(colors: [Color(0xFF061A55), Color(0xFF12358E)]),
            borderRadius: BorderRadius.circular(18),
          ),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Row(children: [Icon(Icons.support_agent_rounded, color: Colors.white, size: 30), SizedBox(width: 10), Expanded(child: Text('Support Vendeur OVANIE', style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w900)))]),
            const SizedBox(height: 8),
            Text(requester['name'] == null ? 'Votre boutique est identifiée automatiquement.' : 'Compte : ${requester['name']}', style: const TextStyle(color: Color(0xFFDDE7FF), height: 1.35)),
            const SizedBox(height: 15),
            SizedBox(width: double.infinity, child: FilledButton.icon(onPressed: _compose, style: FilledButton.styleFrom(backgroundColor: menuOrange, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(11))), icon: const Icon(Icons.add_comment_outlined), label: const Text('Nouvelle demande', style: TextStyle(fontWeight: FontWeight.w900)))),
          ]),
        ),
        const SizedBox(height: 14),
        Row(children: [
          Expanded(child: _Metric(value: '$open', label: 'À suivre', icon: Icons.pending_actions_rounded)),
          const SizedBox(width: 10),
          Expanded(child: _Metric(value: '${_tickets.length}', label: 'Total', icon: Icons.folder_open_rounded)),
        ]),
        const SizedBox(height: 18),
        Row(children: [const Expanded(child: Text('Mes dossiers', style: TextStyle(color: menuBlue, fontSize: 17, fontWeight: FontWeight.w900))), IconButton(onPressed: _load, icon: const Icon(Icons.refresh_rounded, color: menuBlue))]),
        if (_tickets.isEmpty)
          const _EmptySupport()
        else
          ..._tickets.map((ticket) => _TicketCard(ticket: ticket, onTap: () => _openTicket((ticket['id'] as num).toInt()))),
        const SizedBox(height: 12),
        const _InfoCard(),
      ],
    );
  }
}

InputDecoration _input(String label) => InputDecoration(labelText: label, filled: true, fillColor: const Color(0xFFF7F9FD), border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFDCE4F1))), enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFDCE4F1))));
String _contextLabel(String type) => const {'vendor_payout': 'versement', 'order': 'commande', 'shop': 'boutique', 'product': 'produit', 'shipment': 'livraison', 'return': 'retour'}[type] ?? type;
String _statusLabel(dynamic raw) => const {'open': 'Nouveau', 'in_progress': 'Pris en charge', 'waiting': 'En attente', 'waiting_customer': 'Votre réponse attendue', 'waiting_internal': 'Vérification OVANIE en cours', 'resolved': 'Terminé', 'closed': 'Archivé'}['$raw'] ?? '$raw';
Color _statusColor(dynamic raw) => ['resolved', 'closed'].contains('$raw') ? const Color(0xFF18864B) : '$raw' == 'waiting_customer' ? const Color(0xFFD77900) : const Color(0xFF1C5ED6);

class _ContextBadge extends StatelessWidget { const _ContextBadge({required this.label}); final String label; @override Widget build(BuildContext context) => Container(padding: const EdgeInsets.all(10), decoration: BoxDecoration(color: const Color(0xFFEEF4FF), borderRadius: BorderRadius.circular(10)), child: Row(children: [const Icon(Icons.link_rounded, color: menuBlue, size: 18), const SizedBox(width: 8), Expanded(child: Text(label, style: const TextStyle(color: menuBlue, fontWeight: FontWeight.w700, fontSize: 12))) ])); }
class _Metric extends StatelessWidget { const _Metric({required this.value, required this.label, required this.icon}); final String value,label; final IconData icon; @override Widget build(BuildContext context)=>Container(padding: const EdgeInsets.all(14),decoration:BoxDecoration(color:Colors.white,borderRadius:BorderRadius.circular(14),border:Border.all(color:const Color(0xFFE4E9F2))),child:Row(children:[CircleAvatar(backgroundColor:const Color(0xFFEEF4FF),child:Icon(icon,color:menuBlue)),const SizedBox(width:10),Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(value,style:const TextStyle(color:menuBlue,fontSize:20,fontWeight:FontWeight.w900)),Text(label,style:const TextStyle(color:menuMuted,fontSize:11.5))]) ])); }
class _TicketCard extends StatelessWidget { const _TicketCard({required this.ticket,required this.onTap}); final Map<String,dynamic> ticket; final VoidCallback onTap; @override Widget build(BuildContext context)=>Padding(padding:const EdgeInsets.only(bottom:10),child:Material(color:Colors.white,borderRadius:BorderRadius.circular(14),child:InkWell(onTap:onTap,borderRadius:BorderRadius.circular(14),child:Container(padding:const EdgeInsets.all(14),decoration:BoxDecoration(border:Border.all(color:const Color(0xFFE4E9F2)),borderRadius:BorderRadius.circular(14)),child:Row(crossAxisAlignment:CrossAxisAlignment.start,children:[CircleAvatar(backgroundColor:_statusColor(ticket['status']).withValues(alpha:.10),child:Icon(Icons.headset_mic_outlined,color:_statusColor(ticket['status']))),const SizedBox(width:12),Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text('${ticket['reference'] ?? 'SUP'}',style:const TextStyle(color:menuMuted,fontSize:11,fontWeight:FontWeight.w700)),const SizedBox(height:2),Text('${ticket['subject'] ?? 'Demande support'}',maxLines:2,overflow:TextOverflow.ellipsis,style:const TextStyle(color:menuBlue,fontSize:14,fontWeight:FontWeight.w900)),const SizedBox(height:7),Text(_statusLabel(ticket['status']),style:TextStyle(color:_statusColor(ticket['status']),fontSize:11.5,fontWeight:FontWeight.w800))])),const Icon(Icons.chevron_right_rounded,color:menuMuted)]))))); }
class _EmptySupport extends StatelessWidget { const _EmptySupport(); @override Widget build(BuildContext context)=>Container(padding:const EdgeInsets.all(26),decoration:BoxDecoration(color:Colors.white,borderRadius:BorderRadius.circular(14),border:Border.all(color:const Color(0xFFE4E9F2))),child:const Column(children:[Icon(Icons.mark_chat_read_outlined,color:menuMuted,size:34),SizedBox(height:8),Text('Aucune demande support',style:TextStyle(color:menuBlue,fontWeight:FontWeight.w900)),SizedBox(height:4),Text('Vos demandes et les réponses de l’équipe OVANIE apparaîtront ici.',textAlign:TextAlign.center,style:TextStyle(color:menuMuted,fontSize:12,height:1.4))])); }
class _InfoCard extends StatelessWidget { const _InfoCard(); @override Widget build(BuildContext context)=>Container(padding:const EdgeInsets.all(14),decoration:BoxDecoration(color:const Color(0xFFFFF6EE),borderRadius:BorderRadius.circular(14)),child:const Row(crossAxisAlignment:CrossAxisAlignment.start,children:[Icon(Icons.info_outline_rounded,color:menuOrange),SizedBox(width:10),Expanded(child:Text('Pour une commande, un versement ou une livraison, ouvrez l’assistance depuis l’écran concerné : OVANIE associera automatiquement le bon contexte à votre dossier.',style:TextStyle(color:menuBlue,fontSize:12,height:1.4))) ])); }

class _VendorTicketSheet extends StatefulWidget { const _VendorTicketSheet({required this.ticket,required this.onReply}); final Map<String,dynamic> ticket; final Future<void> Function(String) onReply; @override State<_VendorTicketSheet> createState()=>_VendorTicketSheetState(); }
class _VendorTicketSheetState extends State<_VendorTicketSheet>{ final _reply=TextEditingController(); bool _sending=false; @override void dispose(){_reply.dispose();super.dispose();} @override Widget build(BuildContext context){ final messages=rowsOf(widget.ticket['messages']); return DraggableScrollableSheet(expand:false,initialChildSize:.82,maxChildSize:.95,minChildSize:.55,builder:(context,scroll)=>Padding(padding:const EdgeInsets.fromLTRB(18,12,18,12),child:Column(children:[const SizedBox(width:42,child:Divider(thickness:4,color:Color(0xFFD9E1EE))),Row(children:[Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text('${widget.ticket['reference'] ?? ''}',style:const TextStyle(color:menuMuted,fontSize:11)),Text('${widget.ticket['subject'] ?? ''}',style:const TextStyle(color:menuBlue,fontSize:18,fontWeight:FontWeight.w900))])),Container(padding:const EdgeInsets.symmetric(horizontal:10,vertical:6),decoration:BoxDecoration(color:_statusColor(widget.ticket['status']).withValues(alpha:.1),borderRadius:BorderRadius.circular(999)),child:Text(_statusLabel(widget.ticket['status']),style:TextStyle(color:_statusColor(widget.ticket['status']),fontSize:11,fontWeight:FontWeight.w800)))]),const Divider(height:22),Expanded(child:ListView(controller:scroll,children:[if ('${widget.ticket['description'] ?? ''}'.isNotEmpty) Container(padding:const EdgeInsets.all(12),decoration:BoxDecoration(color:const Color(0xFFF5F7FB),borderRadius:BorderRadius.circular(12)),child:Text('${widget.ticket['description']}',style:const TextStyle(color:menuBlue,height:1.4))),const SizedBox(height:12),...messages.map((m){final mine=['vendor','client','driver','commercial'].contains('${m['author_type']}');return Align(alignment:mine?Alignment.centerRight:Alignment.centerLeft,child:Container(margin:const EdgeInsets.only(bottom:8),padding:const EdgeInsets.all(11),constraints:BoxConstraints(maxWidth:MediaQuery.sizeOf(context).width*.76),decoration:BoxDecoration(color:mine?const Color(0xFFEFF4FF):const Color(0xFFF1F5F3),borderRadius:BorderRadius.circular(12)),child:Text('${m['body'] ?? ''}',style:const TextStyle(color:menuBlue,height:1.35))));})])),if(!['closed','cancelled'].contains('${widget.ticket['status']}')) Row(children:[Expanded(child:TextField(controller:_reply,minLines:1,maxLines:4,decoration:_input('Votre réponse'))),const SizedBox(width:8),IconButton.filled(onPressed:_sending?null:()async{final text=_reply.text.trim();if(text.isEmpty)return;setState(()=>_sending=true);try{await widget.onReply(text);if(context.mounted)Navigator.pop(context);}catch(e){if(context.mounted)ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text(ApiClient.friendlyError(e))));}finally{if(mounted)setState(()=>_sending=false);}},style:IconButton.styleFrom(backgroundColor:menuOrange),icon:_sending?const SizedBox.square(dimension:16,child:CircularProgressIndicator(strokeWidth:2,color:Colors.white)):const Icon(Icons.send_rounded,color:Colors.white))]) ]))); } }
