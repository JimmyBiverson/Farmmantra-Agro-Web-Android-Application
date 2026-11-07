import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../providers/auth_provider.dart';
import '../../../core/exceptions/app_exception.dart';
import '../data/operations_api.dart';

const workspaceGreen = Color(0xff244c39);
const workspaceBackground = Color(0xfff4f6f1);
OperationsApi operationsApi(BuildContext context) =>
    OperationsApi(token: context.read<AuthProvider>().token);
String operationError(Object error) => error is AppException
    ? error.message
    : 'Unable to connect. Please check your connection and try again.';
void showOperationError(BuildContext context, Object error) =>
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(operationError(error)),
        behavior: SnackBarBehavior.floating,
      ),
    );
void openWorkspace(BuildContext context, Widget screen) =>
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => screen));

class WorkspacePage extends StatelessWidget {
  final String title;
  final Widget child;
  final List<Widget>? actions;
  final Widget? floatingActionButton;
  const WorkspacePage({
    super.key,
    required this.title,
    required this.child,
    this.actions,
    this.floatingActionButton,
  });
  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: workspaceBackground,
    appBar: AppBar(
      title: Text(title),
      backgroundColor: workspaceBackground,
      foregroundColor: workspaceGreen,
      titleTextStyle: const TextStyle(
        color: workspaceGreen,
        fontSize: 20,
        fontWeight: FontWeight.w700,
      ),
      centerTitle: false,
      actions: actions,
    ),
    body: SafeArea(
      child: Align(
        alignment: Alignment.topCenter,
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 960),
          child: child,
        ),
      ),
    ),
    floatingActionButton: floatingActionButton,
  );
}

class WorkspaceEmpty extends StatelessWidget {
  final String title, message;
  final IconData icon;
  const WorkspaceEmpty({
    super.key,
    required this.title,
    required this.message,
    this.icon = Icons.inbox_outlined,
  });
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.all(32),
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        CircleAvatar(
          radius: 34,
          backgroundColor: const Color(0xffe1edda),
          child: Icon(icon, size: 32, color: workspaceGreen),
        ),
        const SizedBox(height: 18),
        Text(
          title,
          style: Theme.of(context).textTheme.titleLarge,
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 8),
        Text(
          message,
          textAlign: TextAlign.center,
          style: const TextStyle(color: Color(0xff637269), height: 1.5),
        ),
      ],
    ),
  );
}

class WorkspaceTile extends StatelessWidget {
  final String title, subtitle;
  final IconData icon;
  final VoidCallback onTap;
  const WorkspaceTile({
    super.key,
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.onTap,
  });
  @override
  Widget build(BuildContext context) => Card(
    margin: const EdgeInsets.only(bottom: 12),
    elevation: 0,
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
    child: ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 10),
      leading: CircleAvatar(
        backgroundColor: const Color(0xffedf3e8),
        child: Icon(icon, color: workspaceGreen),
      ),
      title: Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
      subtitle: Text(subtitle),
      trailing: const Icon(Icons.arrow_forward_rounded, size: 19),
      onTap: onTap,
    ),
  );
}

class StatusPill extends StatelessWidget {
  final String value;
  const StatusPill(this.value, {super.key});
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
    decoration: BoxDecoration(
      color: value == 'declined' || value == 'rejected'
          ? const Color(0xfffbe9e3)
          : const Color(0xffeaf0df),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      value.replaceAll('_', ' ').toUpperCase(),
      style: const TextStyle(
        fontSize: 10,
        fontWeight: FontWeight.w800,
        letterSpacing: .7,
        color: workspaceGreen,
      ),
    ),
  );
}

class RemoteRecords extends StatefulWidget {
  final String path;
  final Widget Function(BuildContext, Map<String, dynamic>, VoidCallback)
  builder;
  final String emptyTitle, emptyMessage;
  const RemoteRecords({
    super.key,
    required this.path,
    required this.builder,
    this.emptyTitle = 'Nothing here yet',
    this.emptyMessage = 'Your records will appear here as work is submitted.',
  });
  @override
  State<RemoteRecords> createState() => RemoteRecordsState();
}

class RemoteRecordsState extends State<RemoteRecords> {
  late OperationsApi api;
  List<Map<String, dynamic>> records = [];
  String? error;
  bool busy = true;
  bool more = false;
  int page = 1;
  int generation = 0;
  @override
  void initState() {
    super.initState();
    api = operationsApi(context);
    reload();
  }

  @override
  void didUpdateWidget(RemoteRecords oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.path != widget.path) {
      reload();
    }
  }

  Future<void> reload() async {
    page = 1;
    await load(reset: true);
  }

  Future<void> load({bool reset = false}) async {
    final ticket = ++generation;
    if (mounted && ticket == generation) {
      setState(() => busy = true);
    }
    try {
      final response = await api.get(
        '${widget.path}${widget.path.contains('?') ? '&' : '?'}page=$page',
      );
      dynamic pagination = response['data'] is Map
          ? response['data']
          : response;
      if (mounted && ticket == generation) {
        setState(() {
          records = reset
              ? OperationsApi.rows(response)
              : [...records, ...OperationsApi.rows(response)];
          error = null;
          more = pagination is Map && pagination['next_page_url'] != null;
        });
      }
    } catch (e) {
      if (mounted && ticket == generation) {
        setState(() => error = operationError(e));
      }
    } finally {
      if (mounted && ticket == generation) {
        setState(() => busy = false);
      }
    }
  }

  @override
  void dispose() {
    api.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: reload,
    child: ListView(
      padding: const EdgeInsets.all(20),
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        if (error != null)
          Card(
            child: ListTile(
              title: Text(error!),
              trailing: TextButton(
                onPressed: reload,
                child: const Text('Retry'),
              ),
            ),
          ),
        if (busy && records.isEmpty)
          const Padding(
            padding: EdgeInsets.all(40),
            child: Center(child: CircularProgressIndicator()),
          ),
        if (!busy && error == null && records.isEmpty)
          WorkspaceEmpty(
            title: widget.emptyTitle,
            message: widget.emptyMessage,
          ),
        ...records.map((record) => widget.builder(context, record, reload)),
        if (more)
          TextButton(
            onPressed: busy
                ? null
                : () {
                    page++;
                    load();
                  },
            child: Text(busy ? 'Loading…' : 'Load more'),
          ),
        const SizedBox(height: 80),
      ],
    ),
  );
}

Future<Map<String, String>?> reviewNotes(
  BuildContext context, {
  required String title,
  bool payment = false,
}) async {
  final notes = TextEditingController(), reference = TextEditingController();
  final key = GlobalKey<FormState>();
  final result = await showDialog<Map<String, String>>(
    context: context,
    builder: (context) => AlertDialog(
      title: Text(title),
      content: SingleChildScrollView(
        child: Form(
          key: key,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextFormField(
                controller: notes,
                maxLines: 3,
                decoration: const InputDecoration(labelText: 'Review notes'),
                validator: (v) =>
                    (v?.trim().isEmpty ?? true) ? 'Enter review notes' : null,
              ),
              if (payment) ...[
                const SizedBox(height: 14),
                TextFormField(
                  controller: reference,
                  decoration: const InputDecoration(
                    labelText: 'Bank / payment reference',
                  ),
                  validator: (v) => (v?.trim().isEmpty ?? true)
                      ? 'Enter the disbursement reference'
                      : null,
                ),
              ],
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Cancel'),
        ),
        FilledButton(
          onPressed: () {
            if (key.currentState!.validate()) {
              Navigator.pop(context, {
                'notes': notes.text.trim(),
                'reference': reference.text.trim(),
              });
            }
          },
          child: const Text('Confirm'),
        ),
      ],
    ),
  );
  // Controllers live until the dialog's exit animation has completed.
  Future<void>.delayed(const Duration(milliseconds: 350), () {
    notes.dispose();
    reference.dispose();
  });
  return result;
}
