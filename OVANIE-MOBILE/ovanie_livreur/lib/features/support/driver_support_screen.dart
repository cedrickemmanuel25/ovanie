import 'package:flutter/material.dart';

import '../../app/theme.dart';
import '../../core/network/api_client.dart';
import '../missions/widgets/mission_ui.dart';

class DriverSupportScreen extends StatefulWidget {
  const DriverSupportScreen({super.key, this.initialMissionNumber});
  final String? initialMissionNumber;

  @override
  State<DriverSupportScreen> createState() => _DriverSupportScreenState();
}

class _DriverSupportScreenState extends State<DriverSupportScreen> {
  Map<String, dynamic> _data = const {};
  bool _loading = true;
  String? _error;

  Map<String, dynamic> _map(dynamic value) => value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
  List<Map<String, dynamic>> _rows(dynamic value) => value is List ? value.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList() : <Map<String, dynamic>>[];

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    if (mounted) setState(() { _loading = true; _error = null; });
    try {
      final response = await ApiClient.dio.get<dynamic>('/driver/support');
      ApiClient.ensureSuccess(response);
      if (!mounted) return;
      final body = _map(response.data);
      setState(() => _data = _map(body['data']));
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<Map<String, dynamic>> get _tickets => _rows(_data['tickets']);

  Future<void> _compose() async {
    var category = widget.initialMissionNumber == null ? 'general' : 'mission';
    final subject = TextEditingController(text: widget.initialMissionNumber == null ? '' : 'Assistance mission ${widget.initialMissionNumber}');
    final description = TextEditingController();
    final mission = TextEditingController(text: widget.initialMissionNumber ?? '');
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
                const Center(child: SizedBox(width: 42, child: Divider(thickness: 4, color: Color(0xFFD9E4DF)))),
                const Text('Contacter l’assistance', style: TextStyle(color: MissionPalette.navy, fontSize: 21, fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                const Text('Votre identité de livreur partenaire est transmise automatiquement. Ajoutez la mission si votre problème la concerne.', style: TextStyle(color: MissionPalette.slate, height: 1.4)),
                const SizedBox(height: 16),
                DropdownButtonFormField<String>(
                  initialValue: category,
                  decoration: _field('Catégorie'),
                  items: const [
                    DropdownMenuItem(value: 'general', child: Text('Question générale')),
                    DropdownMenuItem(value: 'mission', child: Text('Mission / retrait / livraison')),
                    DropdownMenuItem(value: 'earnings', child: Text('Gain / rémunération')),
                    DropdownMenuItem(value: 'account', child: Text('Compte / profil')),
                    DropdownMenuItem(value: 'technical', child: Text('Application / GPS')),
                  ],
                  onChanged: sending ? null : (v) { if (v != null) setSheetState(() => category = v); },
                ),
                if (category == 'mission') ...[
                  const SizedBox(height: 12),
                  TextField(controller: mission, decoration: _field('N° de mission (ex. OVL-00025)')),
                ],
                const SizedBox(height: 12),
                TextField(controller: subject, decoration: _field('Objet')),
                const SizedBox(height: 12),
                TextField(controller: description, minLines: 5, maxLines: 9, decoration: _field('Décrivez précisément le problème')),
                const SizedBox(height: 18),
                SizedBox(width: double.infinity, height: 50, child: FilledButton.icon(
                  onPressed: sending ? null : () async {
                    if (subject.text.trim().length < 4 || description.text.trim().length < 10) {
                      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Renseignez un objet et une description suffisamment détaillée.')));
                      return;
                    }
                    setSheetState(() => sending = true);
                    try {
                      final response = await ApiClient.dio.post<dynamic>('/driver/support/tickets', data: {
                        'category': category,
                        'subject': subject.text.trim(),
                        'description': description.text.trim(),
                        if (category == 'mission' && mission.text.trim().isNotEmpty) 'mission_number': mission.text.trim(),
                      });
                      ApiClient.ensureSuccess(response);
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
                  style: FilledButton.styleFrom(backgroundColor: OvanieColors.green, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
                  icon: sending ? const SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.send_outlined),
                  label: const Text('Envoyer au support', style: TextStyle(fontWeight: FontWeight.w900)),
                )),
              ]),
            ),
          ),
        ),
      ),
    );
    subject.dispose(); description.dispose(); mission.dispose();
  }

  Future<void> _openTicket(int id) async {
    try {
      final response = await ApiClient.dio.get<dynamic>('/driver/support/tickets/$id');
      ApiClient.ensureSuccess(response);
      final ticket = _map(_map(response.data)['data']);
      if (!mounted) return;
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        backgroundColor: Colors.white,
        shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(26))),
        builder: (_) => _DriverTicketSheet(ticket: ticket, onReply: (message) async {
          final reply = await ApiClient.dio.post<dynamic>('/driver/support/tickets/$id/messages', data: {'message': message});
          ApiClient.ensureSuccess(reply);
        }),
      );
      await _load();
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.friendlyError(error))));
    }
  }

  @override
  Widget build(BuildContext context) {
    final requester = _map(_data['requester']);
    final active = _tickets.where((e) => !['resolved','closed','cancelled'].contains('${e['status']}')).length;
    return Scaffold(
      backgroundColor: MissionPalette.mint,
      body: Column(children: [
        const MissionPageHeader(title: 'Aide & support', subtitle: 'Assistance pour les livreurs partenaires'),
        Expanded(child: RefreshIndicator(
          color: OvanieColors.green,
          onRefresh: _load,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
            children: [
              if (_loading && _data.isEmpty) const Padding(padding: EdgeInsets.symmetric(vertical: 80), child: Center(child: CircularProgressIndicator(color: OvanieColors.green)))
              else ...[
                if (_error != null) _ErrorCard(message: _error!, onRetry: _load),
                Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(gradient: const LinearGradient(colors: [Color(0xFF0B3D2E), Color(0xFF147A55)]), borderRadius: BorderRadius.circular(18)),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    const Row(children: [Icon(Icons.support_agent_rounded, color: Colors.white, size: 30), SizedBox(width: 10), Expanded(child: Text('Support OVANIE Logistics', style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w900)))]),
                    const SizedBox(height: 7),
                    Text(requester['name'] == null ? 'Votre compte partenaire est identifié automatiquement.' : 'Livreur partenaire : ${requester['name']}', style: const TextStyle(color: Color(0xFFDDF4EA), height: 1.4)),
                    const SizedBox(height: 14),
                    SizedBox(width: double.infinity, child: FilledButton.icon(onPressed: _compose, style: FilledButton.styleFrom(backgroundColor: Colors.white, foregroundColor: OvanieColors.greenDark), icon: const Icon(Icons.add_comment_outlined), label: const Text('Nouvelle demande', style: TextStyle(fontWeight: FontWeight.w900)))),
                  ]),
                ),
                const SizedBox(height: 12),
                Row(children: [
                  Expanded(child: _Metric(value: '$active', label: 'À suivre', icon: Icons.pending_actions_rounded)),
                  const SizedBox(width: 10),
                  Expanded(child: _Metric(value: '${_tickets.length}', label: 'Total', icon: Icons.folder_open_rounded)),
                ]),
                const SizedBox(height: 18),
                const Text('Mes demandes', style: TextStyle(color: MissionPalette.navy, fontSize: 17, fontWeight: FontWeight.w900)),
                const SizedBox(height: 9),
                if (_tickets.isEmpty) const _Empty() else ..._tickets.map((ticket) => _TicketCard(ticket: ticket, onTap: () => _openTicket((ticket['id'] as num).toInt()))),
                const SizedBox(height: 12),
                Container(padding: const EdgeInsets.all(14), decoration: BoxDecoration(color: const Color(0xFFE9F6F0), borderRadius: BorderRadius.circular(14), border: Border.all(color: const Color(0xFFCDE8DB))), child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(Icons.info_outline_rounded, color: OvanieColors.green), SizedBox(width: 10), Expanded(child: Text('Un incident opérationnel pendant une mission reste signalé depuis l’écran de mission. Cette page sert à contacter le Support pour votre compte, l’application, vos gains ou un dossier nécessitant de l’aide.', style: TextStyle(color: MissionPalette.slate, fontSize: 12, height: 1.4))) ])),
              ],
            ],
          ),
        )),
      ]),
    );
  }
}

InputDecoration _field(String label) => InputDecoration(labelText: label, filled: true, fillColor: const Color(0xFFF7FAF8), border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFD6E4DD))), enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFD6E4DD))));
String _status(dynamic s) => const {'open':'Nouveau','in_progress':'Pris en charge','waiting':'En attente','waiting_customer':'Votre réponse attendue','waiting_internal':'Vérification OVANIE en cours','resolved':'Terminé','closed':'Archivé'}['$s'] ?? '$s';
Color _statusColor(dynamic s) => ['resolved','closed'].contains('$s') ? OvanieColors.green : '$s' == 'waiting_customer' ? const Color(0xFFD77C00) : const Color(0xFF2469C8);

class _Metric extends StatelessWidget { const _Metric({required this.value,required this.label,required this.icon}); final String value,label; final IconData icon; @override Widget build(BuildContext context)=>MissionSurfaceCard(child:Row(children:[CircleAvatar(backgroundColor:const Color(0xFFE9F6F0),child:Icon(icon,color:OvanieColors.green)),const SizedBox(width:10),Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(value,style:const TextStyle(color:MissionPalette.navy,fontSize:20,fontWeight:FontWeight.w900)),Text(label,style:const TextStyle(color:MissionPalette.slate,fontSize:11.5))]) ])); }
class _TicketCard extends StatelessWidget { const _TicketCard({required this.ticket,required this.onTap}); final Map<String,dynamic> ticket; final VoidCallback onTap; @override Widget build(BuildContext context)=>Padding(padding:const EdgeInsets.only(bottom:9),child:MissionSurfaceCard(child:InkWell(onTap:onTap,borderRadius:BorderRadius.circular(14),child:Row(crossAxisAlignment:CrossAxisAlignment.start,children:[CircleAvatar(backgroundColor:_statusColor(ticket['status']).withValues(alpha:.10),child:Icon(Icons.headset_mic_outlined,color:_statusColor(ticket['status']))),const SizedBox(width:12),Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text('${ticket['reference']??''}',style:const TextStyle(color:MissionPalette.slate,fontSize:11)),Text('${ticket['subject']??'Demande support'}',maxLines:2,overflow:TextOverflow.ellipsis,style:const TextStyle(color:MissionPalette.navy,fontSize:14,fontWeight:FontWeight.w900)),const SizedBox(height:5),Text(_status(ticket['status']),style:TextStyle(color:_statusColor(ticket['status']),fontWeight:FontWeight.w800,fontSize:11))])),const Icon(Icons.chevron_right_rounded,color:MissionPalette.slate)])))); }
class _Empty extends StatelessWidget { const _Empty(); @override Widget build(BuildContext context)=>MissionSurfaceCard(child:const Padding(padding:EdgeInsets.symmetric(vertical:18),child:Column(children:[Icon(Icons.mark_chat_read_outlined,color:MissionPalette.slate,size:36),SizedBox(height:8),Text('Aucune demande support',style:TextStyle(color:MissionPalette.navy,fontWeight:FontWeight.w900)),SizedBox(height:4),Text('Vos demandes et les réponses OVANIE apparaîtront ici.',textAlign:TextAlign.center,style:TextStyle(color:MissionPalette.slate,fontSize:12))]))); }
class _ErrorCard extends StatelessWidget { const _ErrorCard({required this.message,required this.onRetry}); final String message; final Future<void> Function() onRetry; @override Widget build(BuildContext context)=>Padding(padding:const EdgeInsets.only(bottom:12),child:MissionSurfaceCard(child:Row(children:[const Icon(Icons.cloud_off_rounded,color:OvanieColors.green),const SizedBox(width:10),Expanded(child:Text(message,style:const TextStyle(color:MissionPalette.slate,fontSize:12))),TextButton(onPressed:onRetry,child:const Text('Réessayer'))]))); }

class _DriverTicketSheet extends StatefulWidget { const _DriverTicketSheet({required this.ticket,required this.onReply}); final Map<String,dynamic> ticket; final Future<void> Function(String) onReply; @override State<_DriverTicketSheet> createState()=>_DriverTicketSheetState(); }
class _DriverTicketSheetState extends State<_DriverTicketSheet>{final _reply=TextEditingController();bool sending=false;List<Map<String,dynamic>> _rows(dynamic v)=>v is List?v.whereType<Map>().map((e)=>Map<String,dynamic>.from(e)).toList():[];@override void dispose(){_reply.dispose();super.dispose();}@override Widget build(BuildContext context){final messages=_rows(widget.ticket['messages']);return DraggableScrollableSheet(expand:false,initialChildSize:.84,maxChildSize:.96,minChildSize:.55,builder:(context,scroll)=>Padding(padding:const EdgeInsets.all(16),child:Column(children:[const SizedBox(width:42,child:Divider(thickness:4,color:Color(0xFFD9E4DF))),Row(children:[Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text('${widget.ticket['reference']??''}',style:const TextStyle(color:MissionPalette.slate,fontSize:11)),Text('${widget.ticket['subject']??''}',style:const TextStyle(color:MissionPalette.navy,fontSize:18,fontWeight:FontWeight.w900))])),Text(_status(widget.ticket['status']),style:TextStyle(color:_statusColor(widget.ticket['status']),fontWeight:FontWeight.w800,fontSize:11))]),const Divider(height:22),Expanded(child:ListView(controller:scroll,children:[if('${widget.ticket['description']??''}'.isNotEmpty)Container(padding:const EdgeInsets.all(12),decoration:BoxDecoration(color:const Color(0xFFF2F7F4),borderRadius:BorderRadius.circular(12)),child:Text('${widget.ticket['description']}',style:const TextStyle(color:MissionPalette.navy,height:1.4))),const SizedBox(height:10),...messages.map((m){final mine='${m['author_type']}'=='driver';return Align(alignment:mine?Alignment.centerRight:Alignment.centerLeft,child:Container(margin:const EdgeInsets.only(bottom:8),padding:const EdgeInsets.all(11),constraints:BoxConstraints(maxWidth:MediaQuery.sizeOf(context).width*.76),decoration:BoxDecoration(color:mine?const Color(0xFFE8F5EE):const Color(0xFFF1F4F7),borderRadius:BorderRadius.circular(12)),child:Text('${m['body']??''}',style:const TextStyle(color:MissionPalette.navy,height:1.35))));})])),if(!['closed','cancelled'].contains('${widget.ticket['status']}'))Row(children:[Expanded(child:TextField(controller:_reply,minLines:1,maxLines:4,decoration:_field('Votre réponse'))),const SizedBox(width:8),IconButton.filled(onPressed:sending?null:()async{final text=_reply.text.trim();if(text.isEmpty)return;setState(()=>sending=true);try{await widget.onReply(text);if(context.mounted)Navigator.pop(context);}catch(e){if(context.mounted)ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text(ApiClient.friendlyError(e))));}finally{if(mounted)setState(()=>sending=false);}},style:IconButton.styleFrom(backgroundColor:OvanieColors.green),icon:sending?const SizedBox.square(dimension:16,child:CircularProgressIndicator(strokeWidth:2,color:Colors.white)):const Icon(Icons.send,color:Colors.white))])])));}}
