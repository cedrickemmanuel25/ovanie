import 'package:flutter/material.dart';

import '../../../core/theme/ovanie_colors.dart';
import '../data/menu_service.dart';
import '../models/menu_data.dart';
import 'menu_shared.dart';

class CommercialSynchronizationScreen extends StatefulWidget {
  const CommercialSynchronizationScreen({
    super.key,
    required this.menuService,
    required this.initialUser,
    required this.initialProfile,
    required this.unreadNotifications,
  });

  final MenuService menuService;
  final Map<String, dynamic> initialUser;
  final MenuProfileData? initialProfile;
  final int unreadNotifications;

  @override
  State<CommercialSynchronizationScreen> createState() => _CommercialSynchronizationScreenState();
}

class _CommercialSynchronizationScreenState extends State<CommercialSynchronizationScreen> {
  MenuSyncData? _sync;
  MenuPreferencesData? _preferences;
  bool _loading = true;
  bool _syncing = false;
  String? _error;

  @override void initState(){super.initState();_load();}

  Future<void> _load() async{
    if(mounted)setState((){_loading=true;_error=null;});
    try{
      final values=await Future.wait<dynamic>([widget.menuService.syncStatus(),widget.menuService.preferences()]);
      if(mounted)setState((){_sync=values[0] as MenuSyncData;_preferences=values[1] as MenuPreferencesData;});
    }catch(e){if(mounted)setState(()=>_error=e.toString().replaceFirst('ApiException: ',''));}finally{if(mounted)setState(()=>_loading=false);}
  }

  Future<void> _syncNow() async{
    setState(()=>_syncing=true);
    try{final d=await widget.menuService.synchronizeNow();if(mounted){setState(()=>_sync=d);ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content:Text('Synchronisation terminée.')));}}catch(e){if(mounted)ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text(e.toString().replaceFirst('ApiException: ',''))));}finally{if(mounted)setState(()=>_syncing=false);}
  }

  Future<void> _save(MenuPreferencesData p) async{
    setState(()=>_preferences=p);
    try{final saved=await widget.menuService.savePreferences(p);if(mounted)setState(()=>_preferences=saved);}catch(_){if(mounted)ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content:Text('Impossible d’enregistrer ce réglage.')));}
  }

  @override Widget build(BuildContext context){
    final s=menuScaleOf(context).call;
    final profile=menuHeaderProfile(initialUser:widget.initialUser,loaded:widget.initialProfile);
    return MenuPageScaffold(
      profile:profile,
      unreadNotifications:widget.unreadNotifications,
      showHeader:false,
      headerTitle:'Synchronisation',
      headerSubtitle:'Vos données toujours à jour',
      onBack:()=>Navigator.pop(context),
      onNotificationsTap:()=>Navigator.pop(context),
      body:RefreshIndicator(
        onRefresh:_load,
        child:ListView(
          padding:EdgeInsets.fromLTRB(s(10),s(4),s(10),s(102)),
          children:[
            LoadingOrError(
              loading:_loading,
              error:_error,
              onRetry:_load,
              child:Column(children:[
                DarkMenuCard(
                  padding:EdgeInsets.all(s(16)),
                  child:Column(crossAxisAlignment:CrossAxisAlignment.stretch,children:[
                    Align(
                      alignment:Alignment.centerRight,
                      child:Container(padding:EdgeInsets.symmetric(horizontal:s(10),vertical:s(6)),decoration:BoxDecoration(color:const Color(0xFF064B3A),borderRadius:BorderRadius.circular(s(12)),border:Border.all(color:const Color(0xFF087E59))),child:Row(mainAxisSize:MainAxisSize.min,children:[Container(width:s(7),height:s(7),decoration:BoxDecoration(color:_sync?.synced==false?OvanieColors.orange:const Color(0xFF22DD86),shape:BoxShape.circle)),SizedBox(width:s(6)),Text(_sync?.synced==false?'À synchroniser':'Synchronisé',style:TextStyle(color:_sync?.synced==false?const Color(0xFFFFA54A):const Color(0xFF2DE18D),fontSize:s(10.5))) ])),
                    ),
                    SizedBox(height:s(8)),
                    Row(children:[
                      Container(width:s(92),height:s(92),decoration:BoxDecoration(shape:BoxShape.circle,border:Border.all(color:menuBlue,width:s(2)),boxShadow:[BoxShadow(color:menuBlue.withOpacity(.2),blurRadius:s(16))]),child:Center(child:Container(width:s(68),height:s(68),decoration:BoxDecoration(color:const Color(0xFFDCF1FF),borderRadius:BorderRadius.circular(s(22))),child:Icon(Icons.cloud_sync_rounded,color:const Color(0xFF0B5FC5),size:s(40))))),
                      SizedBox(width:s(16)),
                      Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(_sync?.synced==false?'Synchronisation requise':'Tout est synchronisé !',style:TextStyle(color:Colors.white,fontSize:s(20),fontWeight:FontWeight.w800)),SizedBox(height:s(6)),Text(_sync?.synced==false?'Certaines données doivent être synchronisées.':'Vos données sont à jour sur tous vos appareils.',style:TextStyle(color:const Color(0xFFC8D2E2),fontSize:s(11.2))),SizedBox(height:s(7)),Text('Dernière synchronisation : ${_lastSyncText(_sync?.lastSync)}',style:TextStyle(color:const Color(0xFFC8D2E2),fontSize:s(10.2)))])),
                    ]),
                  ]),
                ),
                SizedBox(height:s(10)),
                WhiteMenuCard(
                  padding:EdgeInsets.all(s(12)),
                  child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[_SyncHeader(icon:Icons.cloud_done_outlined,title:'Éléments synchronisés',subtitle:'Ces données sont automatiquement synchronisées avec nos serveurs.'),SizedBox(height:s(7)),...(_sync?.elements??const <SyncElementData>[]).map((e)=>_SyncRow(e)).toList()]),
                ),
                SizedBox(height:s(10)),
                WhiteMenuCard(
                  padding:EdgeInsets.all(s(12)),
                  child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[_SyncHeader(icon:Icons.cloud_sync_outlined,title:'Synchronisation manuelle',subtitle:'Vous pouvez lancer une synchronisation manuelle à tout moment.'),SizedBox(height:s(10)),FilledButton.icon(onPressed:_syncing?null:_syncNow,style:FilledButton.styleFrom(backgroundColor:const Color(0xFF0754B5),minimumSize:Size.fromHeight(s(46))),icon:_syncing?SizedBox(width:s(18),height:s(18),child:const CircularProgressIndicator(strokeWidth:2,color:Colors.white)):Icon(Icons.sync_rounded,size:s(22)),label:Text(_syncing?'Synchronisation...':'Synchroniser maintenant')),SizedBox(height:s(7)),Row(children:[Icon(Icons.info_outline_rounded,color:const Color(0xFF0B65D7),size:s(18)),SizedBox(width:s(7)),Expanded(child:Text('Une connexion Internet est nécessaire pour synchroniser vos données.',style:TextStyle(color:const Color(0xFF516A9D),fontSize:s(9.2))))])]),
                ),
                SizedBox(height:s(10)),
                WhiteMenuCard(
                  padding:EdgeInsets.all(s(12)),
                  child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[_SyncHeader(icon:Icons.settings_outlined,title:'Paramètres de synchronisation',subtitle:'Configurez le comportement de la synchronisation.'),SizedBox(height:s(7)),_SyncToggle(icon:Icons.sync_rounded,color:const Color(0xFF15C86F),title:'Synchronisation automatique',subtitle:'Synchroniser les données en arrière-plan',value:_preferences?.autoSync??true,onChanged:(v){final p=_preferences;if(p!=null)_save(p.copyWith(autoSync:v));}),_SyncToggle(icon:Icons.wifi_rounded,color:const Color(0xFF11BE65),title:'Synchroniser uniquement en Wi-Fi',subtitle:'Économiser vos données mobiles',value:_preferences?.wifiOnly??false,onChanged:(v){final p=_preferences;if(p!=null)_save(p.copyWith(wifiOnly:v));}),_SyncToggle(icon:Icons.notifications_none_rounded,color:OvanieColors.orange,title:'Notifications de synchronisation',subtitle:'Être informé après chaque synchronisation',value:_preferences?.syncNotifications??true,onChanged:(v){final p=_preferences;if(p!=null)_save(p.copyWith(syncNotifications:v));},last:true)]),
                ),
              ]),
            ),
          ],
        ),
      ),
    );
  }

  String _lastSyncText(DateTime? d){if(d==null)return '—';final now=DateTime.now();final x=d.toLocal();final today=DateTime(now.year,now.month,now.day);final day=DateTime(x.year,x.month,x.day);final time='${x.hour.toString().padLeft(2,'0')}:${x.minute.toString().padLeft(2,'0')}';return day==today?'aujourd’hui à $time':menuDate(d);}
}

class _SyncHeader extends StatelessWidget{const _SyncHeader({required this.icon,required this.title,required this.subtitle});final IconData icon;final String title,subtitle;@override Widget build(BuildContext context){final s=menuScaleOf(context).call;return Row(children:[Container(width:s(42),height:s(42),decoration:BoxDecoration(gradient:const LinearGradient(colors:[Color(0xFF0077FF),Color(0xFF004AC7)]),borderRadius:BorderRadius.circular(s(10))),child:Icon(icon,color:Colors.white,size:s(23))),SizedBox(width:s(10)),Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(title,style:TextStyle(color:const Color(0xFF071A43),fontSize:s(14),fontWeight:FontWeight.w800)),Text(subtitle,style:TextStyle(color:const Color(0xFF526C9D),fontSize:s(9.7)))]))]);}}
class _SyncRow extends StatelessWidget{const _SyncRow(this.data);final SyncElementData data;@override Widget build(BuildContext context){final s=menuScaleOf(context).call;final lower=data.title.toLowerCase();final IconData icon=lower.contains('client')?Icons.groups_2_outlined:lower.contains('boutique')?Icons.storefront_outlined:lower.contains('produit')?Icons.inventory_2_outlined:lower.contains('activité')?Icons.description_outlined:Icons.settings_outlined;return Container(padding:EdgeInsets.symmetric(vertical:s(7)),decoration:const BoxDecoration(border:Border(bottom:BorderSide(color:Color(0xFFE1E6EE)))),child:Row(children:[Container(width:s(42),height:s(42),decoration:BoxDecoration(color:const Color(0xFFE8F2FF),borderRadius:BorderRadius.circular(s(10))),child:Icon(icon,color:const Color(0xFF0B63D2),size:s(22))),SizedBox(width:s(10)),Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(data.title,style:TextStyle(color:const Color(0xFF081B45),fontSize:s(11.2),fontWeight:FontWeight.w800)),Text(data.subtitle,style:TextStyle(color:const Color(0xFF6075A0),fontSize:s(9.2)))])),Text(data.time,style:TextStyle(color:const Color(0xFF6279AA),fontSize:s(9.5))),SizedBox(width:s(10)),Container(width:s(25),height:s(25),decoration:const BoxDecoration(color:Color(0xFF1FC56E),shape:BoxShape.circle),child:Icon(Icons.check_rounded,color:Colors.white,size:s(17)))]));}}
class _SyncToggle extends StatelessWidget{const _SyncToggle({required this.icon,required this.color,required this.title,required this.subtitle,required this.value,required this.onChanged,this.last=false});final IconData icon;final Color color;final String title,subtitle;final bool value,last;final ValueChanged<bool> onChanged;@override Widget build(BuildContext context){final s=menuScaleOf(context).call;return Container(padding:EdgeInsets.symmetric(vertical:s(7)),decoration:BoxDecoration(border:last?null:const Border(bottom:BorderSide(color:Color(0xFFE0E5ED)))),child:Row(children:[Container(width:s(38),height:s(38),decoration:BoxDecoration(color:color.withOpacity(.16),borderRadius:BorderRadius.circular(s(9))),child:Icon(icon,color:color,size:s(21))),SizedBox(width:s(9)),Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(title,style:TextStyle(color:const Color(0xFF071A43),fontSize:s(10.8),fontWeight:FontWeight.w800)),Text(subtitle,style:TextStyle(color:const Color(0xFF58709C),fontSize:s(8.9)))])),Transform.scale(scale:.78,child:Switch(value:value,onChanged:onChanged))]));}}
