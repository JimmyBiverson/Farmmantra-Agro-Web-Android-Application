import 'package:flutter/material.dart';

/// Material 3 destinations with safe-area spacing and clear selected states.
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
  Widget build(BuildContext context) => DecoratedBox(
    decoration: BoxDecoration(
      color: Colors.white,
      border: Border(
        top: BorderSide(
          color: Theme.of(context).colorScheme.primary.withValues(alpha: .08),
        ),
      ),
    ),
    child: NavigationBar(
      selectedIndex: currentIndex,
      onDestinationSelected: onTap,
      backgroundColor: Colors.white,
      elevation: 0,
      height: 76,
      indicatorColor: const Color(0xffe1edda),
      labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
      destinations: items
          .map(
            (item) => NavigationDestination(
              icon: item.icon,
              selectedIcon: item.activeIcon,
              label: item.label ?? '',
            ),
          )
          .toList(),
    ),
  );
}
