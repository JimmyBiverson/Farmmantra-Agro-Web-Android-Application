import 'package:flutter/material.dart';

class WorkspaceAction {
  final String title, subtitle;
  final IconData icon;
  final WidgetBuilder builder;
  const WorkspaceAction(this.title, this.subtitle, this.icon, this.builder);
}

class RoleWorkspace {
  final String title, subtitle;
  final List<WorkspaceAction> actions;
  const RoleWorkspace(this.title, this.subtitle, this.actions);
}
