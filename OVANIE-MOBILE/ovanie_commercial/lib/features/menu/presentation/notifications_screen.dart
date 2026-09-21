import 'package:flutter/material.dart';

import '../../../core/theme/ovanie_colors.dart';
import '../data/menu_service.dart';
import '../models/menu_data.dart';
import 'menu_shared.dart';
import 'preferences_screen.dart';

class CommercialNotificationsScreen extends StatefulWidget {
  const CommercialNotificationsScreen({
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
  State<CommercialNotificationsScreen> createState() => _CommercialNotificationsScreenState();
}

class _CommercialNotificationsScreenState extends State<CommercialNotificationsScreen> {
  List<MenuNotificationData> _items = const [];
  bool _loading = true;
  String? _error;
  String _filter = 'all';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (mounted) setState(() { _loading = true; _error = null; });
    try {
      final items = await widget.menuService.notifications();
      if (mounted) setState(() => _items = items);
    } catch (e) {
      if (mounted) setState(() => _error = e.toString().replaceFirst('ApiException: ', ''));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  int get _unread => _items.where((e) => !e.read).length;

  List<MenuNotificationData> get _filtered {
    return switch (_filter) {
      'unread' => _items.where((e) => !e.read).toList(),
      'sessions' => _items.where((e) => e.type == 'session').toList(),
      'system' => _items.where((e) => e.type == 'system').toList(),
      _ => _items,
    };
  }

  Future<void> _markAll() async {
    await widget.menuService.markAllNotificationsRead();
    if (!mounted) return;
    setState(() {
      _items = _items.map((e) => MenuNotificationData(
        id: e.id,
        title: e.title,
        message: e.message,
        type: e.type,
        read: true,
        createdAt: e.createdAt,
      )).toList(growable: false);
    });
  }

  Future<void> _open(MenuNotificationData item) async {
    if (!item.read) {
      try { await widget.menuService.markNotificationRead(item.id); } catch (_) {}
      if (mounted) {
        setState(() {
          _items = _items.map((e) => e.id == item.id
              ? MenuNotificationData(id: e.id, title: e.title, message: e.message, type: e.type, read: true, createdAt: e.createdAt)
              : e).toList(growable: false);
        });
      }
    }
    if (!mounted) return;
    showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(item.title),
        content: Text(item.message.isEmpty ? 'Notification OVANIE Commercial.' : item.message),
        actions: [TextButton(onPressed: () => Navigator.pop(context), child: const Text('Fermer'))],
      ),
    );
  }

  void _openPreferences() {
    Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => CommercialPreferencesScreen(
        menuService: widget.menuService,
        initialUser: widget.initialUser,
        initialProfile: widget.initialProfile,
        unreadNotifications: _unread,
      ),
    ));
  }

  @override
  Widget build(BuildContext context) {
    final s = menuScaleOf(context).call;
    final profile = menuHeaderProfile(initialUser: widget.initialUser, loaded: widget.initialProfile);
    return MenuPageScaffold(
      profile: profile,
      unreadNotifications: _unread,
      onNotificationsTap: () {},
      onAvatarTap: () => Navigator.pop(context),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: EdgeInsets.fromLTRB(s(16), s(10), s(16), s(102)),
          children: [
            const MenuSearchBar(hint: 'Rechercher une boutique, un produit, une référence...'),
            SizedBox(height: s(12)),
            DarkMenuCard(
              padding: EdgeInsets.all(s(10)),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Notifications', style: TextStyle(color: Colors.white, fontSize: s(23), fontWeight: FontWeight.w800)),
                      SizedBox(height: s(4)),
                      Text('Suivez les alertes importantes liées à vos activités terrain.', style: TextStyle(color: const Color(0xFFC1CBDB), fontSize: s(11), height: 1.35)),
                      SizedBox(height: s(6)),
                      Align(
                        alignment: Alignment.centerRight,
                        child: Wrap(
                          spacing: s(4),
                          runSpacing: s(2),
                          crossAxisAlignment: WrapCrossAlignment.center,
                          children: [
                            TextButton.icon(onPressed: _markAll, icon: Icon(Icons.check_circle_outline_rounded, size: s(17)), label: const Text('Tout marquer comme lu')),
                            TextButton.icon(onPressed: _openPreferences, icon: Icon(Icons.settings_outlined, size: s(17)), label: const Text('Paramètres')),
                          ],
                        ),
                      ),
                    ],
                  ),
                  SizedBox(height: s(10)),
                  Row(
                    children: [
                      Expanded(child: _FilterButton(label: 'Toutes', count: _items.length, selected: _filter == 'all', onTap: () => setState(() => _filter = 'all'))),
                      SizedBox(width: s(8)),
                      Expanded(child: _FilterButton(label: 'Non lues', count: _unread, orangeCount: true, selected: _filter == 'unread', onTap: () => setState(() => _filter = 'unread'))),
                      SizedBox(width: s(8)),
                      Expanded(child: _FilterButton(label: 'Sessions', selected: _filter == 'sessions', onTap: () => setState(() => _filter = 'sessions'))),
                      SizedBox(width: s(8)),
                      Expanded(child: _FilterButton(label: 'Système', selected: _filter == 'system', onTap: () => setState(() => _filter = 'system'))),
                    ],
                  ),
                  SizedBox(height: s(12)),
                  LoadingOrError(
                    loading: _loading,
                    error: _error,
                    onRetry: _load,
                    child: _filtered.isEmpty
                        ? Container(
                            padding: EdgeInsets.symmetric(vertical: s(32)),
                            alignment: Alignment.center,
                            child: Text('Aucune notification dans cette catégorie.', style: TextStyle(color: const Color(0xFFC2CCDE), fontSize: s(11))),
                          )
                        : Column(
                            children: [
                              for (var i = 0; i < _filtered.length; i++) ...[
                                _NotificationCard(item: _filtered[i], onTap: () => _open(_filtered[i])),
                                if (i != _filtered.length - 1) SizedBox(height: s(8)),
                              ],
                            ],
                          ),
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

class _FilterButton extends StatelessWidget {
  const _FilterButton({required this.label, required this.selected, required this.onTap, this.count, this.orangeCount = false});
  final String label; final bool selected; final VoidCallback onTap; final int? count; final bool orangeCount;
  @override Widget build(BuildContext context){final s=menuScaleOf(context).call;return InkWell(onTap:onTap,borderRadius:BorderRadius.circular(s(10)),child:Container(height:s(42),decoration:BoxDecoration(color:selected?const Color(0xFF082B57):const Color(0xFF062347),borderRadius:BorderRadius.circular(s(10)),border:Border.all(color:selected?const Color(0xFF1595FF):const Color(0xFF35577E))),child:Row(mainAxisAlignment:MainAxisAlignment.center,children:[Flexible(child:Text(label,overflow:TextOverflow.ellipsis,style:TextStyle(color:Colors.white,fontSize:s(10.5)))),if(count!=null&&count!>0)...[SizedBox(width:s(6)),Container(constraints:BoxConstraints(minWidth:s(19)),height:s(19),padding:EdgeInsets.symmetric(horizontal:s(4)),alignment:Alignment.center,decoration:BoxDecoration(color:orangeCount?OvanieColors.orange:const Color(0xFF176DCC),shape:BoxShape.circle),child:Text('$count',style:TextStyle(color:Colors.white,fontSize:s(8.5),fontWeight:FontWeight.w800)))]])));}
}

class _NotificationCard extends StatelessWidget {
  const _NotificationCard({required this.item, required this.onTap});
  final MenuNotificationData item; final VoidCallback onTap;

  (IconData, Color) get visual => switch(item.type){
    'session' => (Icons.assignment_outlined, const Color(0xFF1D9BFF)),
    'shop' => (Icons.storefront_outlined, const Color(0xFF1D9BFF)),
    'draft' => (Icons.warning_amber_rounded, const Color(0xFFFF7600)),
    'sync' => (Icons.cloud_done_outlined, const Color(0xFF24C56A)),
    'system' => (Icons.system_update_alt_rounded, const Color(0xFF168CFF)),
    _ => (Icons.notifications_none_rounded, const Color(0xFF168CFF)),
  };

  @override Widget build(BuildContext context){final s=menuScaleOf(context).call;final v=visual;return InkWell(onTap:onTap,borderRadius:BorderRadius.circular(s(13)),child:Container(padding:EdgeInsets.all(s(12)),decoration:BoxDecoration(color:const Color(0xFF062A58),borderRadius:BorderRadius.circular(s(13)),border:Border.all(color:item.read?const Color(0xFF31537A):const Color(0xFF1A9BFF),width:item.read?1:1.4)),child:Row(children:[Container(width:s(52),height:s(52),decoration:BoxDecoration(shape:BoxShape.circle,color:v.$2.withOpacity(.12),border:Border.all(color:v.$2)),child:Icon(v.$1,color:v.$2,size:s(27))),SizedBox(width:s(14)),Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(item.title,maxLines:1,overflow:TextOverflow.ellipsis,style:TextStyle(color:Colors.white,fontSize:s(14.2),fontWeight:FontWeight.w800)),SizedBox(height:s(3)),Text(item.message,maxLines:2,overflow:TextOverflow.ellipsis,style:TextStyle(color:const Color(0xFFC6D0E1),fontSize:s(10.8),height:1.3)),if(!item.read)...[SizedBox(height:s(6)),Container(padding:EdgeInsets.symmetric(horizontal:s(7),vertical:s(3)),decoration:BoxDecoration(color:const Color(0xFF073C77),borderRadius:BorderRadius.circular(s(10))),child:Text('Nouveau',style:TextStyle(color:menuBlue,fontSize:s(8.5))))]])),SizedBox(width:s(8)),Column(crossAxisAlignment:CrossAxisAlignment.end,children:[Text(menuRelativeDate(item.createdAt),style:TextStyle(color:const Color(0xFFC1CBDB),fontSize:s(9.4))),SizedBox(height:s(12)),if(!item.read)Container(width:s(8),height:s(8),decoration:BoxDecoration(color:v.$2,shape:BoxShape.circle))else Icon(Icons.chevron_right_rounded,color:Colors.white,size:s(20))])])));}
}
