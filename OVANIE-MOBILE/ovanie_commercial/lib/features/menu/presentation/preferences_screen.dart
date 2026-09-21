import 'package:flutter/material.dart';

import '../../../core/theme/ovanie_colors.dart';
import '../data/menu_service.dart';
import '../models/menu_data.dart';
import 'menu_shared.dart';

class CommercialPreferencesScreen extends StatefulWidget {
  const CommercialPreferencesScreen({
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
  State<CommercialPreferencesScreen> createState() => _CommercialPreferencesScreenState();
}

class _CommercialPreferencesScreenState extends State<CommercialPreferencesScreen> {
  MenuPreferencesData? _data;
  bool _loading = true;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (mounted) setState(() { _loading = true; _error = null; });
    try {
      final data = await widget.menuService.preferences();
      if (mounted) setState(() => _data = data);
    } catch (e) {
      if (mounted) setState(() => _error = e.toString().replaceFirst('ApiException: ', ''));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _save(MenuPreferencesData next) async {
    setState(() { _data = next; _saving = true; });
    try {
      final saved = await widget.menuService.savePreferences(next);
      if (mounted) setState(() => _data = saved);
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString().replaceFirst('ApiException: ', ''))));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _reset() async {
    const defaults = MenuPreferencesData(
      newClients: true,
      followUpReminders: true,
      shopUpdates: true,
      news: false,
      language: 'Français',
      region: 'Abidjan',
      theme: 'light',
      autoSync: true,
      wifiOnly: false,
      shareLocation: true,
      syncNotifications: true,
    );
    await _save(defaults);
  }

  @override
  Widget build(BuildContext context) {
    final s = menuScaleOf(context).call;
    final profile = menuHeaderProfile(initialUser: widget.initialUser, loaded: widget.initialProfile);
    return MenuPageScaffold(
      profile: profile,
      unreadNotifications: widget.unreadNotifications,
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: EdgeInsets.fromLTRB(s(10), s(4), s(10), s(102)),
          children: [
            MenuPageTitle(
              title: 'Préférences',
              subtitle: 'Personnalisez votre expérience sur OVANIE Commercial.',
              onBack: () => Navigator.pop(context),
            ),
            SizedBox(height: s(10)),
            LoadingOrError(
              loading: _loading,
              error: _error,
              onRetry: _load,
              child: _data == null
                  ? const SizedBox.shrink()
                  : Column(
                      children: [
                        _PreferenceSection(
                          icon: Icons.notifications_none_rounded,
                          title: 'Notifications',
                          subtitle: 'Choisissez les informations que vous souhaitez recevoir.',
                          child: Column(
                            children: [
                              _ToggleRow(title: 'Nouveaux clients', subtitle: 'Être informé des nouveaux clients qui me sont attribués.', value: _data!.newClients, onChanged: (v) => _save(_data!.copyWith(newClients: v))),
                              _ToggleRow(title: 'Rappels de suivi', subtitle: 'Recevoir des rappels pour mes tâches et rendez-vous.', value: _data!.followUpReminders, onChanged: (v) => _save(_data!.copyWith(followUpReminders: v))),
                              _ToggleRow(title: 'Mises à jour de mes boutiques', subtitle: 'Être alerté des validations ou changements de statut.', value: _data!.shopUpdates, onChanged: (v) => _save(_data!.copyWith(shopUpdates: v))),
                              _ToggleRow(title: 'Actualités OVANIE', subtitle: 'Recevoir les nouveautés, promotions et informations importantes.', value: _data!.news, onChanged: (v) => _save(_data!.copyWith(news: v)), last: true),
                            ],
                          ),
                        ),
                        SizedBox(height: s(8)),
                        _PreferenceSection(
                          icon: Icons.language_rounded,
                          title: 'Langue et région',
                          subtitle: 'Définissez votre langue d’affichage et votre zone de travail.',
                          child: Row(
                            children: [
                              Expanded(child: _DropdownField(label: 'Langue de l’application', value: _data!.language, values: const ['Français'], onChanged: (v) => _save(_data!.copyWith(language: v)))),
                              SizedBox(width: s(8)),
                              Expanded(child: _DropdownField(label: 'Région par défaut', value: _data!.region, values: const ['Abidjan', 'Bouaké', 'San Pedro', 'Yamoussoukro'], onChanged: (v) => _save(_data!.copyWith(region: v)))),
                            ],
                          ),
                        ),
                        SizedBox(height: s(8)),
                        _PreferenceSection(
                          icon: Icons.dark_mode_outlined,
                          title: 'Mode d’affichage',
                          subtitle: 'Choisissez l’apparence de l’application.',
                          child: Row(
                            children: [
                              Expanded(child: _ThemeChoice(icon: Icons.light_mode_outlined, label: 'Clair', value: 'light', selected: _data!.theme == 'light', onTap: () => _save(_data!.copyWith(theme: 'light')))),
                              SizedBox(width: s(7)),
                              Expanded(child: _ThemeChoice(icon: Icons.dark_mode_outlined, label: 'Sombre', value: 'dark', selected: _data!.theme == 'dark', onTap: () => _save(_data!.copyWith(theme: 'dark')))),
                              SizedBox(width: s(7)),
                              Expanded(child: _ThemeChoice(icon: Icons.desktop_windows_outlined, label: 'Système', value: 'system', selected: _data!.theme == 'system', onTap: () => _save(_data!.copyWith(theme: 'system')))),
                            ],
                          ),
                        ),
                        SizedBox(height: s(8)),
                        _PreferenceSection(
                          icon: Icons.cloud_sync_outlined,
                          title: 'Données et synchronisation',
                          subtitle: 'Gérez l’utilisation des données et la synchronisation hors ligne.',
                          child: Column(children: [
                            _ToggleRow(title: 'Synchronisation automatique', subtitle: 'Synchroniser automatiquement lorsque l’application est ouverte.', value: _data!.autoSync, onChanged: (v) => _save(_data!.copyWith(autoSync: v))),
                            _ToggleRow(title: 'Téléchargement en Wi-Fi uniquement', subtitle: 'Utiliser uniquement le Wi-Fi pour les données volumineuses.', value: _data!.wifiOnly, onChanged: (v) => _save(_data!.copyWith(wifiOnly: v)), last: true),
                          ]),
                        ),
                        SizedBox(height: s(8)),
                        _PreferenceSection(
                          icon: Icons.shield_outlined,
                          title: 'Confidentialité',
                          subtitle: 'Gérez vos préférences de confidentialité.',
                          child: _ToggleRow(title: 'Partager ma position', subtitle: 'Utiliser ma position pour améliorer les suggestions de prospects et itinéraires.', value: _data!.shareLocation, onChanged: (v) => _save(_data!.copyWith(shareLocation: v)), last: true),
                        ),
                        SizedBox(height: s(8)),
                        WhiteMenuCard(
                          padding: EdgeInsets.symmetric(horizontal: s(12), vertical: s(10)),
                          child: Row(
                            children: [
                              Container(width: s(42),height:s(42),decoration:BoxDecoration(color:const Color(0xFFFFE8EC),borderRadius:BorderRadius.circular(s(10))),child:Icon(Icons.delete_outline_rounded,color:const Color(0xFFFF1749),size:s(24))),
                              SizedBox(width:s(10)),
                              Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text('Réinitialisation',style:TextStyle(color:const Color(0xFF071A43),fontSize:s(13.2),fontWeight:FontWeight.w800)),Text('Réinitialisez vos préférences par défaut.',style:TextStyle(color:const Color(0xFF52698F),fontSize:s(9.8)))])),
                              OutlinedButton.icon(onPressed:_saving?null:_reset,icon:Icon(Icons.restart_alt_rounded,size:s(18)),label:const Text('Réinitialiser'),style:OutlinedButton.styleFrom(foregroundColor:const Color(0xFFFF1749),side:const BorderSide(color:Color(0xFFFF1749)))),
                            ],
                          ),
                        ),
                        if (_saving) ...[SizedBox(height:s(8)),const LinearProgressIndicator(minHeight:2)],
                      ],
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PreferenceSection extends StatelessWidget {
  const _PreferenceSection({required this.icon,required this.title,required this.subtitle,required this.child});
  final IconData icon;final String title,subtitle;final Widget child;
  @override Widget build(BuildContext context){final s=menuScaleOf(context).call;return WhiteMenuCard(padding:EdgeInsets.all(s(10)),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Row(children:[Container(width:s(42),height:s(42),decoration:BoxDecoration(gradient:const LinearGradient(colors:[Color(0xFF0077FF),Color(0xFF0047C6)]),borderRadius:BorderRadius.circular(s(10))),child:Icon(icon,color:Colors.white,size:s(23))),SizedBox(width:s(10)),Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(title,style:TextStyle(color:const Color(0xFF081C48),fontSize:s(13.5),fontWeight:FontWeight.w800)),Text(subtitle,style:TextStyle(color:const Color(0xFF3D5E98),fontSize:s(9.7)))]))]),SizedBox(height:s(6)),Container(padding:EdgeInsets.symmetric(horizontal:s(8),vertical:s(3)),decoration:BoxDecoration(color:const Color(0xFFFAFBFD),borderRadius:BorderRadius.circular(s(10))),child:child)]));}
}

class _ToggleRow extends StatelessWidget {
  const _ToggleRow({required this.title,required this.subtitle,required this.value,required this.onChanged,this.last=false});final String title,subtitle;final bool value,last;final ValueChanged<bool> onChanged;
  @override Widget build(BuildContext context){final s=menuScaleOf(context).call;return Container(padding:EdgeInsets.symmetric(vertical:s(7)),decoration:BoxDecoration(border:last?null:const Border(bottom:BorderSide(color:Color(0xFFE1E6EE)))),child:Row(children:[Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(title,style:TextStyle(color:const Color(0xFF091C46),fontSize:s(10.8),fontWeight:FontWeight.w700)),Text(subtitle,style:TextStyle(color:const Color(0xFF526C9F),fontSize:s(9.2),height:1.25))])),Transform.scale(scale:.78,child:Switch(value:value,onChanged:onChanged))]));}
}

class _DropdownField extends StatelessWidget {
  const _DropdownField({required this.label,required this.value,required this.values,required this.onChanged});final String label,value;final List<String> values;final ValueChanged<String> onChanged;
  @override Widget build(BuildContext context){final s=menuScaleOf(context).call;final effective=values.contains(value)?value:values.first;return Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(label,style:TextStyle(color:const Color(0xFF091C46),fontSize:s(9.8),fontWeight:FontWeight.w700)),SizedBox(height:s(5)),DropdownButtonFormField<String>(value:effective,isExpanded:true,decoration:InputDecoration(isDense:true,filled:true,fillColor:Colors.white,contentPadding:EdgeInsets.symmetric(horizontal:s(10),vertical:s(10)),border:OutlineInputBorder(borderRadius:BorderRadius.circular(s(8)),borderSide:const BorderSide(color:Color(0xFFC6D1E0)))),items:values.map((e)=>DropdownMenuItem(value:e,child:Text(e,overflow:TextOverflow.ellipsis))).toList(),onChanged:(v){if(v!=null)onChanged(v);})]);}
}

class _ThemeChoice extends StatelessWidget {
  const _ThemeChoice({required this.icon,required this.label,required this.value,required this.selected,required this.onTap});final IconData icon;final String label,value;final bool selected;final VoidCallback onTap;
  @override Widget build(BuildContext context){final s=menuScaleOf(context).call;return InkWell(onTap:onTap,borderRadius:BorderRadius.circular(s(8)),child:Container(height:s(46),decoration:BoxDecoration(color:selected?const Color(0xFFEAF3FF):Colors.white,borderRadius:BorderRadius.circular(s(8)),border:Border.all(color:selected?const Color(0xFF087BFF):const Color(0xFFC7D1DF),width:selected?1.5:1)),child:Row(mainAxisAlignment:MainAxisAlignment.center,children:[Icon(icon,color:const Color(0xFF09215A),size:s(20)),SizedBox(width:s(7)),Flexible(child:Text(label,overflow:TextOverflow.ellipsis,style:TextStyle(color:selected?const Color(0xFF0562E5):const Color(0xFF0B1C45),fontSize:s(10),fontWeight:FontWeight.w600))),if(selected)...[SizedBox(width:s(5)),Icon(Icons.check_circle_rounded,color:const Color(0xFF0562E5),size:s(17))]])));}
}
