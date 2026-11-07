import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:farmmantra/widgets/common/workspace_bottom_bar.dart';
import 'package:farmmantra/features/operations/presentation/workspace_ui.dart';
import 'package:farmmantra/features/operations/presentation/knowledge_screen.dart';
import 'package:farmmantra/core/theme/app_theme.dart';

void main() {
  testWidgets(
    'bottom navigation remains accessible at narrow width and large text',
    (tester) async {
      tester.view.physicalSize = const Size(360, 800);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      var selected = 0;
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.lightTheme,
          home: MediaQuery(
            data: const MediaQueryData(textScaler: TextScaler.linear(1.5)),
            child: Scaffold(
              bottomNavigationBar: WorkspaceBottomBar(
                currentIndex: 0,
                onTap: (i) => selected = i,
                items: const [
                  BottomNavigationBarItem(
                    icon: Icon(Icons.home),
                    label: 'Home',
                  ),
                  BottomNavigationBarItem(
                    icon: Icon(Icons.grid_view),
                    label: 'My work',
                  ),
                  BottomNavigationBarItem(
                    icon: Icon(Icons.menu_book),
                    label: 'Learn',
                  ),
                  BottomNavigationBarItem(
                    icon: Icon(Icons.person),
                    label: 'Profile',
                  ),
                ],
              ),
            ),
          ),
        ),
      );
      await tester.tap(find.text('Learn'));
      expect(selected, 2);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets(
    'published knowledge displays dosage and PHI as scrollable guidance',
    (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.lightTheme,
          home: const KnowledgeDetail(
            article: {
              'title': 'Approved guide',
              'crop': 'Maize',
              'content': {
                'dosage': 'Follow verified label',
                'phi': 'Client approved interval',
              },
            },
          ),
        ),
      );
      expect(find.text('Approved guide'), findsOneWidget);
      await tester.scrollUntilVisible(
        find.text('Client approved interval'),
        200,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.text('Client approved interval'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets('empty library states explain next step', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: WorkspaceEmpty(
            title: 'No assigned locations',
            message: 'Admin will configure field boundaries.',
          ),
        ),
      ),
    );
    expect(find.text('Admin will configure field boundaries.'), findsOneWidget);
  });
}
