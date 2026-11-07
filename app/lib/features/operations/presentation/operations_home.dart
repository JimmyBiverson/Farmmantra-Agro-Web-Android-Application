import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../providers/auth_provider.dart';
import '../../../core/enums/user_role.dart';
import '../../../core/utils/formatters.dart';
import '../../../screens/admin/admin_workspace.dart';
import '../../../screens/sales_head/sales_head_workspace.dart';
import '../../../screens/agronomist/agronomist_workspace.dart';
import '../../../screens/warehouse/warehouse_workspace.dart';
import '../../../screens/franchise/franchise_workspace.dart';
import '../../../screens/finance/finance_workspace.dart';
import '../../../screens/shared/profile/profile_screen.dart';
import '../../../screens/shared/notifications/notification_screen.dart';
import '../../../widgets/common/workspace_bottom_bar.dart';
import '../data/operations_api.dart';
import 'role_workspace.dart';
import 'workspace_ui.dart';
import 'knowledge_screen.dart';

RoleWorkspace workspaceFor(UserRole? role) => switch (role) {
  UserRole.systemAdministrator => adminWorkspace(),
  UserRole.salesHead => salesHeadWorkspace(),
  UserRole.fieldAgronomist => agronomistWorkspace(),
  UserRole.warehouse => warehouseWorkspace(),
  UserRole.financeDepartment => financeWorkspace(),
  _ => franchiseWorkspace(),
};

class OperationsHubScreen extends StatelessWidget {
  final bool embedded;
  const OperationsHubScreen({super.key, this.embedded = false});
  @override
  Widget build(BuildContext context) {
    final config = workspaceFor(context.watch<AuthProvider>().userRole);
    final body = ListView(
      padding: const EdgeInsets.all(22),
      children: [
        Text(
          config.title,
          style: Theme.of(context).textTheme.headlineMedium?.copyWith(
            fontWeight: FontWeight.w800,
            color: workspaceGreen,
          ),
        ),
        const SizedBox(height: 10),
        Text(
          config.subtitle,
          style: const TextStyle(height: 1.5, color: Color(0xff637269)),
        ),
        const SizedBox(height: 24),
        for (final action in config.actions)
          WorkspaceTile(
            title: action.title,
            subtitle: action.subtitle,
            icon: action.icon,
            onTap: () => openWorkspace(context, action.builder(context)),
          ),
      ],
    );
    return embedded
        ? ColoredBox(color: workspaceBackground, child: body)
        : WorkspacePage(title: 'My workspace', child: body);
  }
}

class OperationsShell extends StatefulWidget {
  const OperationsShell({super.key});
  @override
  State<OperationsShell> createState() => _OperationsShellState();
}

class _OperationsShellState extends State<OperationsShell> {
  int index = 0;
  @override
  Widget build(BuildContext context) {
    final role = context.watch<AuthProvider>().userRole;
    return Scaffold(
      backgroundColor: workspaceBackground,
      appBar: AppBar(
        backgroundColor: workspaceBackground,
        foregroundColor: workspaceGreen,
        titleTextStyle: const TextStyle(
          color: workspaceGreen,
          fontSize: 20,
          fontWeight: FontWeight.w700,
        ),
        centerTitle: false,
        title: Text(
          index == 0
              ? 'Farmmantra'
              : index == 1
              ? 'My work'
              : index == 2
              ? 'Knowledge'
              : 'My profile',
        ),
        actions: [
          IconButton(
            tooltip: 'Notifications',
            icon: const Icon(Icons.notifications_none_rounded),
            onPressed: () => openWorkspace(
              context,
              const WorkspacePage(
                title: 'Notifications',
                child: NotificationScreen(),
              ),
            ),
          ),
        ],
      ),
      body: SafeArea(
        child: switch (index) {
          0 => OperationsHome(key: ValueKey(role)),
          1 => const OperationsHubScreen(embedded: true),
          2 => const KnowledgeScreen(embedded: true),
          _ => const ProfileScreen(),
        },
      ),
      bottomNavigationBar: WorkspaceBottomBar(
        currentIndex: index,
        onTap: (v) => setState(() => index = v),
        items: const [
          BottomNavigationBarItem(
            icon: Icon(Icons.home_outlined),
            activeIcon: Icon(Icons.home_rounded),
            label: 'Home',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.grid_view_outlined),
            activeIcon: Icon(Icons.grid_view_rounded),
            label: 'My work',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.menu_book_outlined),
            activeIcon: Icon(Icons.menu_book_rounded),
            label: 'Learn',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.person_outline),
            activeIcon: Icon(Icons.person),
            label: 'Profile',
          ),
        ],
      ),
    );
  }
}

class OperationsHome extends StatefulWidget {
  final bool compact;
  const OperationsHome({super.key, this.compact = false});
  @override
  State<OperationsHome> createState() => _OperationsHomeState();
}

class _OperationsHomeState extends State<OperationsHome> {
  late OperationsApi api;
  Map<String, dynamic>? data;
  String? error;
  bool busy = false;
  Timer? timer;
  @override
  void initState() {
    super.initState();
    api = operationsApi(context);
    load();
    timer = Timer.periodic(const Duration(seconds: 30), (_) {
      if (WidgetsBinding.instance.lifecycleState == AppLifecycleState.resumed) {
        load();
      }
    });
  }

  Future<void> load() async {
    if (busy) return;
    busy = true;
    try {
      final r = await api.get('operations/dashboard');
      if (mounted) {
        setState(() {
          data = Map<String, dynamic>.from(r['data']);
          error = null;
        });
      }
    } catch (e) {
      if (mounted) setState(() => error = operationError(e));
    } finally {
      busy = false;
    }
  }

  @override
  void dispose() {
    timer?.cancel();
    api.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final role = context.watch<AuthProvider>().userRole;
    final config = workspaceFor(role);
    final metrics = <String, dynamic>{
      if (role == UserRole.franchisePartner)
        'Stock value': data?['stock_value'],
      if (role == UserRole.franchisePartner)
        'Monthly margin': data?['monthly_margin'],
      if (role == UserRole.franchisePartner)
        'Outstanding': data?['outstanding'],
      if (role == UserRole.franchisePartner)
        'Sales this month': data?['monthly_sales'],
      if (role == UserRole.financeDepartment ||
          role == UserRole.systemAdministrator)
        'Monthly expenses': data?['monthly_expenses'],
      if (role == UserRole.financeDepartment ||
          role == UserRole.systemAdministrator)
        'Monthly budget': data?['monthly_budget'],
    };
    final body = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (!widget.compact)
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              color: workspaceGreen,
              borderRadius: BorderRadius.circular(26),
              image: const DecorationImage(
                image: AssetImage('assets/images/farmmantra-fields.png'),
                fit: BoxFit.cover,
                opacity: .16,
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  config.title.toUpperCase(),
                  style: const TextStyle(
                    fontSize: 11,
                    letterSpacing: 1.6,
                    color: Color(0xffd0e8b8),
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 16),
                Text(
                  data?['greeting'] ??
                      'Welcome, ${context.watch<AuthProvider>().user?.name ?? 'there'}',
                  style: const TextStyle(
                    fontSize: 28,
                    height: 1.15,
                    fontWeight: FontWeight.w800,
                    color: Colors.white,
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  config.subtitle,
                  style: const TextStyle(color: Color(0xffe2ebdf), height: 1.5),
                ),
                const SizedBox(height: 22),
                Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: [
                    _heroCount('Order reviews', data?['pending_order_reviews']),
                    _heroCount('Requests', data?['pending_requests']),
                  ],
                ),
              ],
            ),
          ),
        if (error != null)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 12),
            child: Card(
              elevation: 0,
              child: ListTile(
                title: Text(error!),
                subtitle: Text(
                  data == null
                      ? 'No data loaded yet.'
                      : 'Showing the last loaded records.',
                ),
                trailing: IconButton(
                  tooltip: 'Retry',
                  onPressed: load,
                  icon: const Icon(Icons.refresh),
                ),
              ),
            ),
          ),
        if (metrics.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 16),
            child: LayoutBuilder(
              builder: (context, c) => Wrap(
                spacing: 12,
                runSpacing: 12,
                children: [
                  for (final metric in metrics.entries)
                    Container(
                      width: (c.maxWidth - 12) / 2,
                      padding: const EdgeInsets.all(18),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            metric.key,
                            style: const TextStyle(
                              color: Color(0xff637269),
                              fontSize: 12,
                            ),
                          ),
                          const SizedBox(height: 12),
                          Text(
                            metric.value == null
                                ? '—'
                                : Formatters.currency(metric.value),
                            style: const TextStyle(
                              fontSize: 20,
                              fontWeight: FontWeight.w800,
                              color: workspaceGreen,
                            ),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
            ),
          ),
        if (data?['budget_used_percentage'] != null)
          Padding(
            padding: const EdgeInsets.only(top: 14),
            child: Text('Budget used: ${data!['budget_used_percentage']}%'),
          ),
        if (data?['achievements'] is Map &&
            (data!['achievements'] as Map).isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 16),
            child: Card(
              elevation: 0,
              margin: EdgeInsets.zero,
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Sales achievements',
                      style: TextStyle(
                        fontWeight: FontWeight.w800,
                        color: workspaceGreen,
                      ),
                    ),
                    for (final period in ['weekly', 'monthly', 'yearly'])
                      Padding(
                        padding: const EdgeInsets.only(top: 14),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              '${period == 'yearly'
                                  ? 'Year to date'
                                  : period == 'monthly'
                                  ? 'This month'
                                  : 'This week'} · ${data!['achievements'][period]?['percentage'] == null ? 'No target set' : '${data!['achievements'][period]['percentage']}%'}',
                            ),
                            const SizedBox(height: 6),
                            LinearProgressIndicator(
                              value:
                                  ((double.tryParse(
                                                '${data!['achievements'][period]?['percentage']}',
                                              ) ??
                                              0) /
                                          100)
                                      .clamp(0, 1),
                              minHeight: 6,
                              borderRadius: BorderRadius.circular(8),
                            ),
                          ],
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ),
        const SizedBox(height: 24),
        Text(
          'Your next action',
          style: Theme.of(
            context,
          ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 14),
        for (final action in config.actions.take(widget.compact ? 3 : 4))
          WorkspaceTile(
            title: action.title,
            subtitle: action.subtitle,
            icon: action.icon,
            onTap: () => openWorkspace(context, action.builder(context)),
          ),
        if ((data?['overdue_orders'] as List?)?.isNotEmpty ?? false) ...[
          const SizedBox(height: 14),
          const Text(
            'Payment attention',
            style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
          ),
          for (final r in (data!['overdue_orders'] as List))
            Card(
              elevation: 0,
              child: ListTile(
                leading: const Icon(Icons.schedule, color: Color(0xffa66a22)),
                title: Text(
                  '${r['number']} · ${Formatters.currency(r['amount'])}',
                ),
                subtitle: Text(
                  'Stock received ${r['days']} days ago · Due ${r['due_at']}',
                ),
              ),
            ),
        ],
        TextButton.icon(
          onPressed: () => openWorkspace(context, const OperationsHubScreen()),
          icon: const Icon(Icons.grid_view_outlined),
          label: const Text('Open all workspace tools'),
        ),
        if (data != null)
          Padding(
            padding: const EdgeInsets.only(top: 8, bottom: 12),
            child: Text(
              'Updated ${Formatters.dateTime(data!['updated_at'])} · live business records',
              style: const TextStyle(fontSize: 11, color: Color(0xff637269)),
            ),
          ),
      ],
    );
    return widget.compact
        ? body
        : RefreshIndicator(
            onRefresh: load,
            child: ListView(
              padding: const EdgeInsets.all(20),
              physics: const AlwaysScrollableScrollPhysics(),
              children: [body],
            ),
          );
  }

  Widget _heroCount(String label, dynamic count) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 10),
    decoration: BoxDecoration(
      color: Colors.white.withValues(alpha: .1),
      borderRadius: BorderRadius.circular(12),
    ),
    child: Text(
      '${count ?? '—'}  $label',
      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
    ),
  );
}
