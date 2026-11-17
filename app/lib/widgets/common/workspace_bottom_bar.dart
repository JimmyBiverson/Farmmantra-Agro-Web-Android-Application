import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

class WorkspaceRouteObserver extends NavigatorObserver {
  final detail = ValueNotifier(false);

  @override
  void didChangeTop(Route<dynamic> topRoute, Route<dynamic>? previousTopRoute) {
    if (topRoute is PopupRoute) return;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (topRoute.isCurrent) detail.value = topRoute is MaterialPageRoute && topRoute.settings is! Page;
    });
  }
}

class WorkspaceFrame extends StatelessWidget {
  const WorkspaceFrame({super.key, required this.observer, required this.home, required this.location, required this.child});
  final WorkspaceRouteObserver observer;
  final String home;
  final String location;
  final Widget child;

  @override
  Widget build(BuildContext context) => ValueListenableBuilder<bool>(
    valueListenable: observer.detail,
    builder: (context, pushed, _) {
      final show = pushed || !location.endsWith('/dashboard');
      return Scaffold(
        resizeToAvoidBottomInset: false,
        body: child,
        bottomNavigationBar: show ? WorkspaceBottomBar(
          currentIndex: !pushed && location == '/operations' ? 2 : -1,
          items: const [
            BottomNavigationBarItem(icon: Icon(Icons.arrow_back), label: 'Back'),
            BottomNavigationBarItem(icon: Icon(Icons.eco_outlined), label: 'Home'),
            BottomNavigationBarItem(icon: Icon(Icons.grid_view_rounded), label: 'Workspace'),
          ],
          onTap: (index) {
            final nav = observer.navigator;
            if (index == 0 && nav != null && nav.canPop()) {
              nav.maybePop();
            } else if (index == 2 && (pushed || location != '/operations')) {
              context.push('/operations');
            } else if (index != 2) {
              nav?.popUntil((route) => route.isFirst);
              context.go(home);
            }
          },
        ) : null,
      );
    },
  );
}

class WorkspaceBottomBar extends StatelessWidget {
  final int currentIndex;
  final ValueChanged<int> onTap;
  final List<BottomNavigationBarItem> items;
  const WorkspaceBottomBar({super.key,required this.currentIndex,required this.onTap,required this.items});

  @override
  Widget build(BuildContext context) {
    const green = Color(0xff26734e);
    return SafeArea(top:false, minimum:const EdgeInsets.fromLTRB(12,4,12,6),
      child: Material(color:Colors.white,elevation:4,borderRadius:BorderRadius.circular(28),
        child: Row(children:[
          for (var i=0;i<items.length;i++)
            Expanded(child: Semantics(selected:i==currentIndex,button:true,label:items[i].label,
              child: Tooltip(message:items[i].label ?? '',child:InkWell(
                borderRadius:BorderRadius.circular(24),onTap:()=>onTap(i),
                child: SizedBox(height:58,child:Column(mainAxisAlignment:MainAxisAlignment.center,children:[
                  Transform.translate(offset:Offset(0,i==items.length~/2 ? -4 : 0),
                    child: DecoratedBox(decoration:BoxDecoration(shape:BoxShape.circle,color:i==items.length~/2 ? green : Colors.transparent),
                      child:Padding(padding:const EdgeInsets.all(6),child:IconTheme(
                        data:IconThemeData(size:22,color:i==items.length~/2 ? Colors.white : i==currentIndex ? green : Colors.blueGrey),
                        child:i==currentIndex ? items[i].activeIcon : items[i].icon)))),
                  AnimatedContainer(duration:const Duration(milliseconds:160),width:i==currentIndex?12:4,height:4,
                    decoration:BoxDecoration(color:i==currentIndex?green:Colors.transparent,borderRadius:BorderRadius.circular(4)))
                ])),
              )),
            )),
        ]),
      ),
    );
  }
}
