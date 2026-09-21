import 'package:flutter/material.dart';

import '../../../core/theme/ovanie_colors.dart';
import '../data/menu_service.dart';
import '../models/menu_data.dart';
import 'menu_shared.dart';

class CommercialVisitedShopsScreen extends StatefulWidget {
  const CommercialVisitedShopsScreen({
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
  State<CommercialVisitedShopsScreen> createState() => _CommercialVisitedShopsScreenState();
}

class _CommercialVisitedShopsScreenState extends State<CommercialVisitedShopsScreen> {
  final _search = TextEditingController();
  VisitedShopsData? _data;
  bool _loading = true;
  String? _error;
  String _filter = 'all';

  @override void initState(){super.initState();_load();}
  @override void dispose(){_search.dispose();super.dispose();}

  Future<void> _load() async {
    if(mounted)setState((){_loading=true;_error=null;});
    try{final d=await widget.menuService.visits();if(mounted)setState(()=>_data=d);}catch(e){if(mounted)setState(()=>_error=e.toString().replaceFirst('ApiException: ',''));}finally{if(mounted)setState(()=>_loading=false);}
  }

  List<VisitedShopData> get _items {
    final data=_data?.items??const <VisitedShopData>[];
    final q=_search.text.trim().toLowerCase();
    final now=DateTime.now();
    final today=DateTime(now.year,now.month,now.day);
    final week=today.subtract(const Duration(days:7));
    return data.where((item){
      final matchesQuery=q.isEmpty||'${item.name} ${item.category} ${item.location} ${item.contact}'.toLowerCase().contains(q);
      if(!matchesQuery)return false;
      final date=item.visitedAt?.toLocal();
      return switch(_filter){
        'today'=>date!=null&&DateTime(date.year,date.month,date.day)==today,
        'week'=>date!=null&&date.isAfter(week),
        'follow'=>item.status=='follow_up',
        'converted'=>item.status=='converted',
        _=>true,
      };
    }).toList(growable:false);
  }

  void _details(VisitedShopData item){showDialog<void>(context:context,builder:(context)=>AlertDialog(title:Text(item.name),content:Text('${item.category}\n${item.location}\n${item.contact.isEmpty?'':item.contact+'\n'}Visite : ${menuDate(item.visitedAt)}${item.nextAction==null?'':'\nProchaine action : ${menuDate(item.nextAction)}'}'),actions:[TextButton(onPressed:()=>Navigator.pop(context),child:const Text('Fermer'))]));}

  @override Widget build(BuildContext context){
    final s=menuScaleOf(context).call;
    final profile=menuHeaderProfile(initialUser:widget.initialUser,loaded:widget.initialProfile);
    final d=_data;
    return MenuPageScaffold(
      profile:profile,
      unreadNotifications:widget.unreadNotifications,
      body:RefreshIndicator(
        onRefresh:_load,
        child:ListView(
          padding:EdgeInsets.fromLTRB(s(14),s(10),s(14),s(102)),
          children:[
            MenuSearchBar(hint:'Rechercher une boutique, un produit, une référence...',controller:_search,onChanged:(_)=>setState((){}),onFilterTap:(){}),
            SizedBox(height:s(12)),
            MenuPageTitle(
              title:'Boutiques visitées',
              subtitle:'Suivez les boutiques déjà visitées et\nreprenez facilement vos actions terrain.',
              action:OutlinedButton.icon(
                onPressed:()=>menuTabNavigate(context,2),
                icon:Icon(Icons.storefront_outlined,size:s(20)),
                label:const Text('Nouvelle visite'),
                style:OutlinedButton.styleFrom(foregroundColor:OvanieColors.orange,side:const BorderSide(color:OvanieColors.orange),padding:EdgeInsets.symmetric(horizontal:s(14),vertical:s(12))),
              ),
            ),
            SizedBox(height:s(10)),
            const MenuInfoStrip(text:'Historique commercial : retrouvez vos visites, les actions menées et les prochaines relances.'),
            SizedBox(height:s(8)),
            SingleChildScrollView(
              scrollDirection:Axis.horizontal,
              child:Row(children:[
                SizedBox(width:s(92),child:_VisitFilter(label:'Toutes',count:d?.all??0,selected:_filter=='all',onTap:()=>setState(()=>_filter='all'))),SizedBox(width:s(7)),
                SizedBox(width:s(100),child:_VisitFilter(label:'Aujourd’hui',count:d?.today??0,selected:_filter=='today',onTap:()=>setState(()=>_filter='today'))),SizedBox(width:s(7)),
                SizedBox(width:s(112),child:_VisitFilter(label:'Cette semaine',count:d?.week??0,selected:_filter=='week',onTap:()=>setState(()=>_filter='week'))),SizedBox(width:s(7)),
                SizedBox(width:s(96),child:_VisitFilter(label:'À relancer',count:d?.followUp??0,selected:_filter=='follow',onTap:()=>setState(()=>_filter='follow'))),SizedBox(width:s(7)),
                SizedBox(width:s(92),child:_VisitFilter(label:'Converties',count:d?.converted??0,selected:_filter=='converted',onTap:()=>setState(()=>_filter='converted'))),
              ]),
            ),
            SizedBox(height:s(9)),
            DarkMenuCard(
              padding:EdgeInsets.symmetric(horizontal:s(14),vertical:s(11)),
              child:Row(children:[
                Expanded(child:_VisitMetric(icon:Icons.storefront_outlined,value:d?.all??0,label:'boutiques visitées\nce mois',color:menuBlue)),
                Container(width:1,height:s(43),color:Colors.white24),
                Expanded(child:_VisitMetric(icon:Icons.history_rounded,value:d?.followUp??0,label:'relances prévues',color:OvanieColors.orange)),
                Container(width:1,height:s(43),color:Colors.white24),
                Expanded(child:_VisitMetric(icon:Icons.trending_up_rounded,value:d?.converted??0,label:'conversions',color:const Color(0xFF33D783))),
              ]),
            ),
            SizedBox(height:s(8)),
            LoadingOrError(
              loading:_loading,
              error:_error,
              onRetry:_load,
              child:_items.isEmpty
                  ?DarkMenuCard(child:Padding(padding:EdgeInsets.symmetric(vertical:s(26)),child:Center(child:Text('Aucune visite enregistrée pour ce filtre.',style:TextStyle(color:const Color(0xFFC4CEE0),fontSize:s(11))))))
                  :Column(children:[for(var i=0;i<_items.length;i++)...[_VisitCard(item:_items[i],onTap:()=>_details(_items[i])),if(i!=_items.length-1)SizedBox(height:s(6))]]),
            ),
            SizedBox(height:s(8)),
            const MenuInfoStrip(text:'Astuce : consultez l’historique de vos visites pour mieux préparer vos relances commerciales.'),
          ],
        ),
      ),
    );
  }
}

class _VisitFilter extends StatelessWidget{
  const _VisitFilter({required this.label,required this.count,required this.selected,required this.onTap});final String label;final int count;final bool selected;final VoidCallback onTap;
  @override Widget build(BuildContext context){final s=menuScaleOf(context).call;return InkWell(onTap:onTap,borderRadius:BorderRadius.circular(s(9)),child:Container(height:s(36),padding:EdgeInsets.symmetric(horizontal:s(5)),decoration:BoxDecoration(color:selected?const Color(0xFF0B75E9):const Color(0xFF06254F),borderRadius:BorderRadius.circular(s(9)),border:Border.all(color:selected?const Color(0xFF1A98FF):const Color(0xFF355A86))),child:Row(mainAxisAlignment:MainAxisAlignment.center,children:[Flexible(child:Text(label,maxLines:1,overflow:TextOverflow.ellipsis,style:TextStyle(color:Colors.white,fontSize:s(9.2)))),SizedBox(width:s(4)),Text('$count',style:TextStyle(color:Colors.white,fontSize:s(9.4),fontWeight:FontWeight.w800))])));}
}

class _VisitMetric extends StatelessWidget{const _VisitMetric({required this.icon,required this.value,required this.label,required this.color});final IconData icon;final int value;final String label;final Color color;@override Widget build(BuildContext context){final s=menuScaleOf(context).call;return Row(mainAxisAlignment:MainAxisAlignment.center,children:[Container(width:s(38),height:s(38),decoration:BoxDecoration(color:color.withOpacity(.12),shape:BoxShape.circle),child:Icon(icon,color:color,size:s(20))),SizedBox(width:s(8)),Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text('$value',style:TextStyle(color:Colors.white,fontSize:s(17),fontWeight:FontWeight.w800)),Text(label,style:TextStyle(color:const Color(0xFFC4CEE0),fontSize:s(8.7),height:1.12))])]);}}

class _VisitCard extends StatelessWidget{
  const _VisitCard({required this.item,required this.onTap});final VisitedShopData item;final VoidCallback onTap;
  Color get statusColor=>switch(item.status){'converted'=>const Color(0xFF0FCC74),'follow_up'=>OvanieColors.orange,'no_follow_up'=>const Color(0xFFA9B5C8),_=>const Color(0xFF19A2FF)};
  @override Widget build(BuildContext context){final s=menuScaleOf(context).call;return DarkMenuCard(padding:EdgeInsets.all(s(8)),child:Row(children:[_VisitImage(url:item.imageUrl,name:item.name),SizedBox(width:s(12)),Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Row(children:[Expanded(child:Text(item.name,maxLines:1,overflow:TextOverflow.ellipsis,style:TextStyle(color:Colors.white,fontSize:s(13.8),fontWeight:FontWeight.w800))),Container(padding:EdgeInsets.symmetric(horizontal:s(8),vertical:s(3)),decoration:BoxDecoration(color:statusColor.withOpacity(.12),borderRadius:BorderRadius.circular(s(10)),border:Border.all(color:statusColor.withOpacity(.65))),child:Row(children:[Container(width:s(6),height:s(6),decoration:BoxDecoration(color:statusColor,shape:BoxShape.circle)),SizedBox(width:s(5)),Text(item.statusLabel,style:TextStyle(color:statusColor,fontSize:s(8.8)))]) )]),SizedBox(height:s(2)),Text(item.category,maxLines:1,overflow:TextOverflow.ellipsis,style:TextStyle(color:menuBlue,fontSize:s(9.8))),SizedBox(height:s(5)),Wrap(spacing:s(13),runSpacing:s(3),children:[_Mini(icon:Icons.location_on_outlined,text:item.location),if(item.contact.isNotEmpty)_Mini(icon:Icons.person_outline_rounded,text:item.contact),_Mini(icon:Icons.calendar_month_outlined,text:'Visite le ${menuDate(item.visitedAt)}')]),SizedBox(height:s(6)),Divider(height:1,color:Colors.white24),SizedBox(height:s(5)),Row(children:[Expanded(child:Text(item.productsCaptured>0?'${item.productsCaptured} produits capturés':'Aucun produit capturé',maxLines:1,overflow:TextOverflow.ellipsis,style:TextStyle(color:item.productsCaptured>0?const Color(0xFF31D58B):const Color(0xFFC1CAD9),fontSize:s(9.2)))),if(item.nextAction!=null)Expanded(child:Text('Prochaine action : ${menuDate(item.nextAction,withTime:false)}',maxLines:1,overflow:TextOverflow.ellipsis,textAlign:TextAlign.right,style:TextStyle(color:const Color(0xFFC1CAD9),fontSize:s(8.6))))])])),SizedBox(width:s(10)),OutlinedButton(onPressed:onTap,style:OutlinedButton.styleFrom(foregroundColor:statusColor,side:BorderSide(color:statusColor),padding:EdgeInsets.symmetric(horizontal:s(12),vertical:s(10))),child:Row(mainAxisSize:MainAxisSize.min,children:[Text(item.status=='follow_up'?'Reprendre':'Voir'),SizedBox(width:s(5)),Icon(Icons.chevron_right_rounded,size:s(18))]))]));}
}

class _VisitImage extends StatelessWidget{const _VisitImage({required this.url,required this.name});final String? url;final String name;@override Widget build(BuildContext context){final s=menuScaleOf(context).call;return ClipRRect(borderRadius:BorderRadius.circular(s(8)),child:SizedBox(width:s(88),height:s(72),child:url==null?Container(color:const Color(0xFF0B3A6B),alignment:Alignment.center,child:Icon(Icons.storefront_outlined,color:Colors.white70,size:s(34))):Image.network(url!,fit:BoxFit.cover,errorBuilder:(_,__,___)=>Container(color:const Color(0xFF0B3A6B),alignment:Alignment.center,child:Icon(Icons.storefront_outlined,color:Colors.white70,size:s(34))))));}}
class _Mini extends StatelessWidget{const _Mini({required this.icon,required this.text});final IconData icon;final String text;@override Widget build(BuildContext context){final s=menuScaleOf(context).call;return Row(mainAxisSize:MainAxisSize.min,children:[Icon(icon,color:const Color(0xFFC9D3E4),size:s(13)),SizedBox(width:s(4)),Text(text,style:TextStyle(color:const Color(0xFFC9D3E4),fontSize:s(8.8)))]);}}
