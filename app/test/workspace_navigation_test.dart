import 'package:farmmantra/widgets/common/workspace_bottom_bar.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

void main() {
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
