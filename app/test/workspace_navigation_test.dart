import 'package:farmmantra/widgets/common/workspace_bottom_bar.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

void main() {
  testWidgets(
    'Home stays centered and other destinations retain their actions',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 480));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      int? tapped;
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            bottomNavigationBar: WorkspaceBottomBar(
              currentIndex: 0,
              onTap: (index) => tapped = index,
              items: const [
                BottomNavigationBarItem(icon: Icon(Icons.home), label: 'Home'),
                BottomNavigationBarItem(
                  icon: Icon(Icons.list),
                  label: 'Orders',
                ),
                BottomNavigationBarItem(
                  icon: Icon(Icons.store),
                  label: 'Stock',
                ),
                BottomNavigationBarItem(
                  icon: Icon(Icons.info),
                  label: 'Alerts',
                ),
                BottomNavigationBarItem(
                  icon: Icon(Icons.person),
                  label: 'Profile',
                ),
              ],
            ),
          ),
        ),
      );

      final home = find.byIcon(Icons.eco_outlined);
      expect(tester.getCenter(home).dx, 160);
      expect(
        tester.getCenter(home).dy,
        lessThan(tester.getCenter(find.byIcon(Icons.list)).dy),
      );
      await tester.tap(home);
      expect(tapped, 0);
      await tester.tap(find.byTooltip('Stock'));
      expect(tapped, 2);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'Back and Home work on pushed screens and directly opened details',
    (tester) async {
      final observer = WorkspaceRouteObserver();
      final router = GoRouter(
        initialLocation: '/staff/dashboard',
        routes: [
          ShellRoute(
            observers: [observer],
            builder: (context, state, child) => WorkspaceFrame(
              observer: observer,
              home: '/staff/dashboard',
              location: state.uri.path,
              child: child,
            ),
            routes: [
              GoRoute(
                path: '/staff/dashboard',
                builder: (c, s) => Scaffold(
                  body: TextButton(
                    child: const Text('View order'),
                    onPressed: () => Navigator.of(c).push(
                      MaterialPageRoute(
                        builder: (c) =>
                            const Scaffold(body: Text('Order detail')),
                      ),
                    ),
                  ),
                ),
              ),
              GoRoute(
                path: '/operations',
                builder: (c, s) => const Scaffold(body: Text('Workspace page')),
              ),
              GoRoute(
                path: '/receipt',
                builder: (c, s) => const Scaffold(body: Text('Receipt detail')),
              ),
            ],
          ),
        ],
      );
      addTearDown(router.dispose);
      await tester.pumpWidget(MaterialApp.router(routerConfig: router));
      await tester.pumpAndSettle();
      await tester.tap(find.text('View order'));
      await tester.pumpAndSettle();
      expect(find.text('Order detail'), findsOneWidget);
      await tester.tap(find.byTooltip('Back'));
      await tester.pumpAndSettle();
      expect(find.text('View order'), findsOneWidget);
      router.go('/receipt');
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Workspace'));
      await tester.pumpAndSettle();
      expect(find.text('Workspace page'), findsOneWidget);
      await tester.tap(find.byTooltip('Home'));
      await tester.pumpAndSettle();
      expect(find.text('View order'), findsOneWidget);
    },
  );
}
