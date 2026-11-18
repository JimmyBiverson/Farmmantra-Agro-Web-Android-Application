import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

class WorkspaceRouteObserver extends NavigatorObserver {
  final detail = ValueNotifier(false);

  @override
  void didChangeTop(Route<dynamic> topRoute, Route<dynamic>? previousTopRoute) {
    if (topRoute is PopupRoute) return;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (topRoute.isCurrent) {
        detail.value =
            topRoute is MaterialPageRoute && topRoute.settings is! Page;
      }
    });
  }
}

class WorkspaceFrame extends StatelessWidget {
  const WorkspaceFrame({
    super.key,
    required this.observer,
    required this.home,
    required this.location,
    required this.child,
  });
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
        bottomNavigationBar: show
            ? WorkspaceBottomBar(
                currentIndex: !pushed && location == '/operations' ? 2 : -1,
                items: const [
                  BottomNavigationBarItem(
                    icon: Icon(Icons.arrow_back),
                    label: 'Back',
                  ),
                  BottomNavigationBarItem(
                    icon: Icon(Icons.eco_outlined),
                    label: 'Home',
                  ),
                  BottomNavigationBarItem(
                    icon: Icon(Icons.grid_view_rounded),
                    label: 'Workspace',
                  ),
                ],
                onTap: (index) {
                  final nav = observer.navigator;
                  if (index == 0 && nav != null && nav.canPop()) {
                    nav.maybePop();
                  } else if (index == 2 &&
                      (pushed || location != '/operations')) {
                    context.push('/operations');
                  } else if (index != 2) {
                    nav?.popUntil((route) => route.isFirst);
                    context.go(home);
                  }
                },
              )
            : null,
      );
    },
  );
}

class WorkspaceBottomBar extends StatelessWidget {
  final int currentIndex;
  final ValueChanged<int> onTap;
  final List<BottomNavigationBarItem> items;
  const WorkspaceBottomBar({
    super.key,
    required this.currentIndex,
    required this.onTap,
    required this.items,
  });

  @override
  Widget build(BuildContext context) {
    const green = Color(0xff2b805e);
    final colors = Theme.of(context).colorScheme;
    final raisedIndex = items.length ~/ 2;
    final order = List<int>.generate(items.length, (index) => index);
    final homeIndex = items.indexWhere(
      (item) => item.label == 'Home' || item.label == 'Dashboard',
    );
    if (homeIndex >= 0) {
      order.removeAt(homeIndex);
      order.insert(raisedIndex, homeIndex);
    }

    return SafeArea(
      top: false,
      minimum: const EdgeInsets.fromLTRB(16, 0, 16, 8),
      child: Center(
        heightFactor: 1,
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 440),
          child: SizedBox(
            height: 72,
            child: Stack(
              children: [
                Positioned.fill(
                  top: 20,
                  child: Material(
                    color: colors.surface,
                    elevation: 2,
                    borderRadius: BorderRadius.circular(20),
                  ),
                ),
                Positioned.fill(
                  child: Row(
                    children: [
                      for (var i = 0; i < items.length; i++)
                        Expanded(
                          child: _WorkspaceDestination(
                            item: items[order[i]],
                            raised: i == raisedIndex,
                            selected: order[i] == currentIndex,
                            green: green,
                            onTap: () => onTap(order[i]),
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _WorkspaceDestination extends StatelessWidget {
  const _WorkspaceDestination({
    required this.item,
    required this.raised,
    required this.selected,
    required this.green,
    required this.onTap,
  });

  final BottomNavigationBarItem item;
  final bool raised;
  final bool selected;
  final Color green;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final icon = IconTheme(
      data: IconThemeData(
        size: 21,
        color: raised
            ? Colors.white
            : selected
            ? green
            : colors.onSurfaceVariant,
      ),
      child: raised
          ? const Icon(Icons.eco_outlined)
          : (selected ? item.activeIcon : item.icon),
    );

    return Padding(
      padding: EdgeInsets.only(top: raised ? 0 : 20),
      child: Semantics(
        label: item.label,
        selected: selected,
        button: true,
        child: Tooltip(
          message: item.label ?? '',
          excludeFromSemantics: true,
          child: Material(
            type: MaterialType.transparency,
            child: InkWell(
              borderRadius: BorderRadius.circular(20),
              onTap: onTap,
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  if (raised)
                    DecoratedBox(
                      decoration: BoxDecoration(
                        color: green,
                        shape: BoxShape.circle,
                        border: Border.all(color: colors.surface, width: 3),
                        boxShadow: const [
                          BoxShadow(
                            color: Color(0x26000000),
                            blurRadius: 5,
                            offset: Offset(0, 2),
                          ),
                        ],
                      ),
                      child: SizedBox.square(
                        dimension: 48,
                        child: Center(child: icon),
                      ),
                    )
                  else
                    icon,
                  const SizedBox(height: 5),
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 160),
                    width: 5,
                    height: 5,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: selected ? green : Colors.transparent,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
