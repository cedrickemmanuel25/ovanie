import 'package:flutter/material.dart';

import '../../core/api/api_client.dart';
import '../../core/theme/ovanie_colors.dart';

class CommercialSupportScreen extends StatefulWidget {
  const CommercialSupportScreen({super.key, required this.api});
  final ApiClient api;

  @override
  State<CommercialSupportScreen> createState() => _CommercialSupportScreenState();
}

class _CommercialSupportScreenState extends State<CommercialSupportScreen> with SingleTickerProviderStateMixin {
  late final TabController _tabs = TabController(length: 2, vsync: this);
  Map<String, dynamic> _center = const {};
  List<Map<String, dynamic>> _transfers = const [];
  bool _loading = true;
  String? _error;

  @override
  void initState() { super.initState(); _load(); }
  @override
  void dispose() { _tabs.dispose(); super.dispose(); }

  Map<String, dynamic> _map(dynamic value) => value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
  List<Map<String, dynamic>> _rows(dynamic value) => value is List ? value.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList() : <Map<String, dynamic>>[];
  String _message(Object error) => error.toString().replaceFirst('ApiException: ', '').replaceFirst('Exception: ', '');

  Future<void> _load() async {
    if (mounted) setState(() { _loading = true; _error = null; });
    try {
      final results = await Future.wait([
        widget.api.getJson('/mobile/v1/commercial/support'),
        widget.api.getJson('/mobile/v1/commercial/support/transfers'),
      ]);
      if (!mounted) return;
      setState(() {
        _center = _map(results[0]['data']);
        _transfers = _rows(results[1]['data']);
      });
    } catch (error) {
      if (mounted) setState(() => _error = _message(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<Map<String, dynamic>> get _tickets => _rows(_center['tickets']);

  Future<void> _compose() async {
    var category = 'commercial';
    final subject = TextEditingController();
    final description = TextEditingController();
    var sending = false;
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(26))),
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) => SafeArea(
          top: false,
          child: Padding(
            padding: EdgeInsets.fromLTRB(18, 14, 18, MediaQuery.viewInsetsOf(context).bottom + 20),
            child: SingleChildScrollView(
              child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Center(child: SizedBox(width: 42, child: Divider(thickness: 4, color: Color(0xFFDCE3EC)))),
                const Text('Nouvelle demande interne', style: TextStyle(color: OvanieColors.navy900, fontSize: 21, fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                const Text('Le Support OVANIE recevra votre demande avec votre identité Commercial.', style: TextStyle(color: OvanieColors.navy700, height: 1.4)),
                const SizedBox(height: 16),
                DropdownButtonFormField<String>(
                  initialValue: category,
                  decoration: _field('Catégorie'),
                  items: const [
                    DropdownMenuItem(value: 'commercial', child: Text('Activité commerciale')),
                    DropdownMenuItem(value: 'account', child: Text('Compte')),
                    DropdownMenuItem(value: 'technical', child: Text('Application / technique')),
                    DropdownMenuItem(value: 'general', child: Text('Autre demande')),
                  ],
                  onChanged: sending ? null : (v) { if (v != null) setSheetState(() => category = v); },
                ),
                const SizedBox(height: 12),
                TextField(controller: subject, decoration: _field('Objet')),
                const SizedBox(height: 12),
                TextField(controller: description, minLines: 5, maxLines: 9, decoration: _field('Description détaillée')),
                const SizedBox(height: 18),
                SizedBox(width: double.infinity, height: 50, child: FilledButton.icon(
                  onPressed: sending ? null : () async {
                    if (subject.text.trim().length < 4 || description.text.trim().length < 10) {
                      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Renseignez un objet et une description suffisamment détaillée.')));
                      return;
                    }
                    setSheetState(() => sending = true);
                    try {
                      await widget.api.postJson('/mobile/v1/commercial/support/tickets', body: {
                        'category': category,
                        'subject': subject.text.trim(),
                        'description': description.text.trim(),
                      });
                      if (!context.mounted) return;
                      Navigator.pop(context);
                      await _load();
                    } catch (error) {
                      if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(_message(error))));
                    } finally {
                      if (context.mounted) setSheetState(() => sending = false);
                    }
                  },
                  style: FilledButton.styleFrom(backgroundColor: OvanieColors.orange, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
                  icon: sending ? const SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.send_outlined),
                  label: const Text('Envoyer au support', style: TextStyle(fontWeight: FontWeight.w900)),
                )),
              ]),
            ),
          ),
        ),
      ),
    );
    subject.dispose(); description.dispose();
  }

  Future<void> _openTicket(int id) async {
    try {
      final response = await widget.api.getJson('/mobile/v1/commercial/support/tickets/$id');
      final ticket = _map(response['data']);
      if (!mounted) return;
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        backgroundColor: Colors.white,
        shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(26))),
        builder: (_) => _CommercialTicketSheet(
          ticket: ticket,
          onReply: (message) => widget.api.postJson('/mobile/v1/commercial/support/tickets/$id/messages', body: {'message': message}),
        ),
      );
      await _load();
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(_message(error))));
    }
  }

  Future<void> _openTransfer(int id) async {
    try {
      final response = await widget.api.getJson('/mobile/v1/commercial/support/transfers/$id');
      final transfer = _map(response['data']);
      if (!mounted) return;
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        backgroundColor: Colors.white,
        shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(26))),
        builder: (_) => _TransferSheet(
          transfer: transfer,
          api: widget.api,
          messageFor: _message,
        ),
      );
      await _load();
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(_message(error))));
    }
  }

  @override
  Widget build(BuildContext context) {
    final openTickets = _tickets.where((e) => !['resolved','closed','cancelled'].contains('${e['status']}')).length;
    final openTransfers = _transfers.where((e) => !['resolved','cancelled'].contains('${e['status']}')).length;
    return Scaffold(
      backgroundColor: const Color(0xFFF4F7FB),
      appBar: AppBar(
        backgroundColor: OvanieColors.navy900,
        foregroundColor: Colors.white,
        title: const Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Assistance & transferts', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 18)),
          Text('Commercial OVANIE', style: TextStyle(fontSize: 11, color: Color(0xFFBFD0E7))),
        ]),
      ),
      body: _loading && _center.isEmpty
          ? const Center(child: CircularProgressIndicator(color: OvanieColors.orange))
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 30),
                children: [
                  if (_error != null) _ErrorCard(message: _error!, onRetry: _load),
                  _Hero(onCreate: _compose),
                  const SizedBox(height: 12),
                  Row(children: [
                    Expanded(child: _Metric(value: '$openTickets', label: 'Demandes à suivre', icon: Icons.support_agent_rounded)),
                    const SizedBox(width: 10),
                    Expanded(child: _Metric(value: '$openTransfers', label: 'Transferts reçus', icon: Icons.move_to_inbox_rounded)),
                  ]),
                  const SizedBox(height: 16),
                  Container(
                    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
                    child: TabBar(
                      controller: _tabs,
                      labelColor: OvanieColors.navy900,
                      unselectedLabelColor: OvanieColors.muted,
                      indicatorColor: OvanieColors.orange,
                      tabs: const [Tab(text: 'Mes demandes'), Tab(text: 'Transferts Support')],
                    ),
                  ),
                  const SizedBox(height: 10),
                  SizedBox(
                    height: 480,
                    child: TabBarView(
                      controller: _tabs,
                      children: [
                        _tickets.isEmpty ? const _Empty(text: 'Aucune demande adressée au Support.') : ListView(children: _tickets.map((t) => _Card(title: '${t['subject'] ?? 'Demande support'}', reference: '${t['reference'] ?? ''}', status: '${t['status'] ?? ''}', icon: Icons.headset_mic_outlined, onTap: () => _openTicket((t['id'] as num).toInt()))).toList()),
                        _transfers.isEmpty ? const _Empty(text: 'Aucun dossier Support transmis au Commercial.') : ListView(children: _transfers.map((t) { final requester=_map(t['requester']); return _Card(title: '${t['reason'] ?? 'Demande commerciale'}', reference: '${t['reference'] ?? ''} • ${requester['name'] ?? 'Client'}', status: '${t['status'] ?? ''}', icon: Icons.business_center_outlined, onTap: () => _openTransfer((t['id'] as num).toInt())); }).toList()),
                      ],
                    ),
                  ),
                ],
              ),
            ),
    );
  }
}

InputDecoration _field(String label) => InputDecoration(labelText: label, filled: true, fillColor: const Color(0xFFF7F9FC), border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFD9E1EB))), enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFD9E1EB))));
String _status(dynamic s) => const {'pending':'À prendre','requested':'À prendre','open':'Nouveau','in_progress':'Pris en charge','waiting':'En attente','waiting_customer':'Votre réponse attendue','waiting_internal':'Vérification OVANIE en cours','resolved':'Terminé','closed':'Archivé'}['$s'] ?? '$s';
Color _statusColor(dynamic s) => ['resolved','closed'].contains('$s') ? OvanieColors.green : ['pending','requested','open'].contains('$s') ? OvanieColors.orange : OvanieColors.blue;

class _Hero extends StatelessWidget { const _Hero({required this.onCreate}); final VoidCallback onCreate; @override Widget build(BuildContext context)=>Container(padding:const EdgeInsets.all(18),decoration:BoxDecoration(gradient:const LinearGradient(colors:[OvanieColors.navy900,OvanieColors.navy700]),borderRadius:BorderRadius.circular(18)),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[const Row(children:[Icon(Icons.hub_outlined,color:Colors.white,size:29),SizedBox(width:10),Expanded(child:Text('Un seul point de contact',style:TextStyle(color:Colors.white,fontSize:18,fontWeight:FontWeight.w900)))]),const SizedBox(height:7),const Text('Demandez de l’aide pour votre application ou traitez les dossiers clients que le Support vous transmet.',style:TextStyle(color:Color(0xFFD7E6F8),height:1.4)),const SizedBox(height:14),SizedBox(width:double.infinity,child:FilledButton.icon(onPressed:onCreate,style:FilledButton.styleFrom(backgroundColor:OvanieColors.orange),icon:const Icon(Icons.add_comment_outlined),label:const Text('Nouvelle demande',style:TextStyle(fontWeight:FontWeight.w900))))])); }
class _Metric extends StatelessWidget { const _Metric({required this.value,required this.label,required this.icon}); final String value,label; final IconData icon; @override Widget build(BuildContext context)=>Container(padding:const EdgeInsets.all(13),decoration:BoxDecoration(color:Colors.white,borderRadius:BorderRadius.circular(14),border:Border.all(color:const Color(0xFFE2E7EF))),child:Row(children:[CircleAvatar(backgroundColor:const Color(0xFFECF5FF),child:Icon(icon,color:OvanieColors.blue)),const SizedBox(width:9),Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(value,style:const TextStyle(color:OvanieColors.navy900,fontSize:19,fontWeight:FontWeight.w900)),Text(label,maxLines:2,style:const TextStyle(color:OvanieColors.muted,fontSize:10.5))]))])); }
class _Card extends StatelessWidget { const _Card({required this.title,required this.reference,required this.status,required this.icon,required this.onTap}); final String title,reference,status; final IconData icon; final VoidCallback onTap; @override Widget build(BuildContext context)=>Card(elevation:0,margin:const EdgeInsets.only(bottom:9),shape:RoundedRectangleBorder(borderRadius:BorderRadius.circular(14),side:const BorderSide(color:Color(0xFFE2E7EF))),child:ListTile(onTap:onTap,contentPadding:const EdgeInsets.symmetric(horizontal:14,vertical:6),leading:CircleAvatar(backgroundColor:_statusColor(status).withValues(alpha:.1),child:Icon(icon,color:_statusColor(status))),title:Text(title,maxLines:2,overflow:TextOverflow.ellipsis,style:const TextStyle(color:OvanieColors.navy900,fontWeight:FontWeight.w800,fontSize:13.5)),subtitle:Padding(padding:const EdgeInsets.only(top:5),child:Text(reference,style:const TextStyle(color:OvanieColors.muted,fontSize:11))),trailing:Column(mainAxisAlignment:MainAxisAlignment.center,crossAxisAlignment:CrossAxisAlignment.end,children:[Text(_status(status),style:TextStyle(color:_statusColor(status),fontSize:10.5,fontWeight:FontWeight.w800)),const Icon(Icons.chevron_right_rounded,color:OvanieColors.muted,size:20)]))); }
class _Empty extends StatelessWidget { const _Empty({required this.text}); final String text; @override Widget build(BuildContext context)=>Center(child:Padding(padding:const EdgeInsets.all(28),child:Column(mainAxisSize:MainAxisSize.min,children:[const Icon(Icons.inbox_outlined,color:OvanieColors.muted,size:38),const SizedBox(height:8),Text(text,textAlign:TextAlign.center,style:const TextStyle(color:OvanieColors.navy700,height:1.4))]))); }
class _ErrorCard extends StatelessWidget { const _ErrorCard({required this.message,required this.onRetry}); final String message; final Future<void> Function() onRetry; @override Widget build(BuildContext context)=>Container(margin:const EdgeInsets.only(bottom:12),padding:const EdgeInsets.all(12),decoration:BoxDecoration(color:const Color(0xFFFFF1F0),borderRadius:BorderRadius.circular(12)),child:Row(children:[const Icon(Icons.error_outline,color:Color(0xFFC0362C)),const SizedBox(width:8),Expanded(child:Text(message,style:const TextStyle(color:Color(0xFF7A2A25),fontSize:12))),TextButton(onPressed:onRetry,child:const Text('Réessayer'))])); }

class _CommercialTicketSheet extends StatefulWidget { const _CommercialTicketSheet({required this.ticket,required this.onReply}); final Map<String,dynamic> ticket; final Future<dynamic> Function(String) onReply; @override State<_CommercialTicketSheet> createState()=>_CommercialTicketSheetState(); }
class _CommercialTicketSheetState extends State<_CommercialTicketSheet>{final _reply=TextEditingController();bool _sending=false;List<Map<String,dynamic>> _rows(dynamic v)=>v is List?v.whereType<Map>().map((e)=>Map<String,dynamic>.from(e)).toList():[];@override void dispose(){_reply.dispose();super.dispose();}@override Widget build(BuildContext context){final messages=_rows(widget.ticket['messages']);return DraggableScrollableSheet(expand:false,initialChildSize:.84,maxChildSize:.96,minChildSize:.55,builder:(context,scroll)=>Padding(padding:const EdgeInsets.all(16),child:Column(children:[const SizedBox(width:42,child:Divider(thickness:4,color:Color(0xFFDCE3EC))),Row(children:[Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text('${widget.ticket['reference']??''}',style:const TextStyle(color:OvanieColors.muted,fontSize:11)),Text('${widget.ticket['subject']??''}',style:const TextStyle(color:OvanieColors.navy900,fontSize:18,fontWeight:FontWeight.w900))])),Text(_status(widget.ticket['status']),style:TextStyle(color:_statusColor(widget.ticket['status']),fontWeight:FontWeight.w800,fontSize:11))]),const Divider(height:22),Expanded(child:ListView(controller:scroll,children:[if('${widget.ticket['description']??''}'.isNotEmpty)Container(padding:const EdgeInsets.all(12),decoration:BoxDecoration(color:const Color(0xFFF5F7FA),borderRadius:BorderRadius.circular(12)),child:Text('${widget.ticket['description']}',style:const TextStyle(color:OvanieColors.navy800,height:1.4))),const SizedBox(height:10),...messages.map((m)=>Container(margin:const EdgeInsets.only(bottom:8),padding:const EdgeInsets.all(11),decoration:BoxDecoration(color:const Color(0xFFEEF5FF),borderRadius:BorderRadius.circular(12)),child:Text('${m['body']??''}',style:const TextStyle(color:OvanieColors.navy900,height:1.35))))])),if(!['closed','cancelled'].contains('${widget.ticket['status']}'))Row(children:[Expanded(child:TextField(controller:_reply,minLines:1,maxLines:4,decoration:_field('Votre réponse'))),const SizedBox(width:8),IconButton.filled(style:IconButton.styleFrom(backgroundColor:OvanieColors.orange),onPressed:_sending?null:()async{final text=_reply.text.trim();if(text.isEmpty)return;setState(()=>_sending=true);try{await widget.onReply(text);if(context.mounted)Navigator.pop(context);}finally{if(mounted)setState(()=>_sending=false);}},icon:_sending?const SizedBox.square(dimension:16,child:CircularProgressIndicator(strokeWidth:2,color:Colors.white)):const Icon(Icons.send,color:Colors.white))])])));}}

class _TransferSheet extends StatefulWidget { const _TransferSheet({required this.transfer,required this.api,required this.messageFor}); final Map<String,dynamic> transfer; final ApiClient api; final String Function(Object) messageFor; @override State<_TransferSheet> createState()=>_TransferSheetState(); }
class _TransferSheetState extends State<_TransferSheet>{late Map<String,dynamic> data=Map<String,dynamic>.from(widget.transfer);final _reply=TextEditingController();bool busy=false;Map<String,dynamic> _map(dynamic v)=>v is Map?Map<String,dynamic>.from(v):{};List<Map<String,dynamic>> _rows(dynamic v)=>v is List?v.whereType<Map>().map((e)=>Map<String,dynamic>.from(e)).toList():[];Future<void> _action(String path,{Map<String,dynamic>? body})async{setState(()=>busy=true);try{final r=await widget.api.postJson('/mobile/v1/commercial/support/transfers/${data['id']}/$path',body:body);if(r['data'] is Map)setState(()=>data=Map<String,dynamic>.from(r['data'] as Map));if(mounted)ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text('${r['message']??'Action enregistrée.'}')));}catch(e){if(mounted)ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text(widget.messageFor(e))));}finally{if(mounted)setState(()=>busy=false);}}@override void dispose(){_reply.dispose();super.dispose();}@override Widget build(BuildContext context){final requester=_map(data['requester']);final messages=_rows(data['messages']);final assigned=data['assigned_to_me']==true;final closed='${data['status']}'=='resolved';return DraggableScrollableSheet(expand:false,initialChildSize:.88,maxChildSize:.97,minChildSize:.6,builder:(context,scroll)=>Padding(padding:const EdgeInsets.all(16),child:Column(children:[const SizedBox(width:42,child:Divider(thickness:4,color:Color(0xFFDCE3EC))),Row(children:[Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text('${data['reference']??''}',style:const TextStyle(color:OvanieColors.muted,fontSize:11)),Text('${data['reason']??'Dossier commercial'}',style:const TextStyle(color:OvanieColors.navy900,fontSize:18,fontWeight:FontWeight.w900))])),Text(_status(data['status']),style:TextStyle(color:_statusColor(data['status']),fontWeight:FontWeight.w800,fontSize:11))]),const Divider(height:20),Expanded(child:ListView(controller:scroll,children:[Container(padding:const EdgeInsets.all(12),decoration:BoxDecoration(color:const Color(0xFFF5F7FA),borderRadius:BorderRadius.circular(12)),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[const Text('Demandeur',style:TextStyle(color:OvanieColors.navy900,fontWeight:FontWeight.w900)),const SizedBox(height:4),Text('${requester['name']??'Client'}',style:const TextStyle(color:OvanieColors.navy700)),if('${requester['phone']??''}'.isNotEmpty)Text('${requester['phone']}',style:const TextStyle(color:OvanieColors.muted,fontSize:12)),const SizedBox(height:8),Text('${data['notes']??''}',style:const TextStyle(color:OvanieColors.navy700,height:1.4))])),const SizedBox(height:10),...messages.map((m)=>Container(margin:const EdgeInsets.only(bottom:8),padding:const EdgeInsets.all(10),decoration:BoxDecoration(color:const Color(0xFFEEF5FF),borderRadius:BorderRadius.circular(10)),child:Text('${m['body']??''}',style:const TextStyle(color:OvanieColors.navy900,height:1.35))))])),if(!closed&&!assigned)SizedBox(width:double.infinity,child:FilledButton.icon(onPressed:busy?null:()=>_action('claim'),style:FilledButton.styleFrom(backgroundColor:OvanieColors.orange),icon:const Icon(Icons.person_add_alt_1_rounded),label:const Text('Prendre en charge',style:TextStyle(fontWeight:FontWeight.w900)))),if(!closed&&assigned)Row(children:[Expanded(child:TextField(controller:_reply,decoration:_field('Répondre au dossier'))),const SizedBox(width:8),IconButton.filled(onPressed:busy?null:()async{final text=_reply.text.trim();if(text.isEmpty)return;await _action('messages',body:{'message':text});if(mounted)_reply.clear();},style:IconButton.styleFrom(backgroundColor:OvanieColors.blue),icon:const Icon(Icons.send,color:Colors.white)),const SizedBox(width:6),IconButton.outlined(onPressed:busy?null:()=>_action('resolve',body:{'note':'Traité depuis l’application Commercial'}),tooltip:'Clôturer',icon:const Icon(Icons.check_circle_outline,color:OvanieColors.green))])])));}}
