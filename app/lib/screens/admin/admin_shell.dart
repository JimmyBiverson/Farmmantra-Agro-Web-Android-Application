import 'package:flutter/material.dart';
import '../../features/operations/presentation/operations_home.dart';

/// AdminShell owns the role entry point; shared workflows live in features/operations.
class AdminShell extends StatelessWidget {
  const AdminShell({super.key});
  @override
  Widget build(BuildContext context) => const OperationsShell();
}
