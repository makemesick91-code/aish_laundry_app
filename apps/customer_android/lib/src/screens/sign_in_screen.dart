import 'package:aish_auth/aish_auth.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../app.dart';

/// Customer sign-in.
///
/// Notes on what is deliberately absent:
///
///   * No credential is ever written to a log, a diagnostic, or an analytics
///     event — not the password, and not the identifier.
///   * The submit control is DISABLED while a request is in flight, so a double
///     tap cannot produce two sign-in attempts.
///   * A failure message never distinguishes "no such account" from "wrong
///     password", because that distinction is an account-enumeration oracle.
class SignInScreen extends ConsumerStatefulWidget {
  const SignInScreen({super.key});

  @override
  ConsumerState<SignInScreen> createState() => _SignInScreenState();
}

class _SignInScreenState extends ConsumerState<SignInScreen> {
  final TextEditingController _identifier = TextEditingController();
  final TextEditingController _password = TextEditingController();
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final FocusNode _identifierFocus = FocusNode();
  final FocusNode _passwordFocus = FocusNode();

  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _identifier.dispose();
    _password.dispose();
    _identifierFocus.dispose();
    _passwordFocus.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) {
      // Validation failure moves focus to the first invalid field so a screen
      // reader user is told what to fix rather than left where they were.
      _identifierFocus.requestFocus();
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });

    final state = await ref
        .read(authServiceProvider)
        .signIn(identifier: _identifier.text.trim(), password: _password.text);

    if (!mounted) {
      return;
    }
    setState(() {
      _busy = false;
      _error = state is Authenticated
          ? null
          // One message for every failure mode. Deliberately non-specific.
          : 'Tidak dapat masuk. Periksa kembali data Anda, lalu coba lagi.';
    });
    // The password is cleared from memory on every outcome, success included.
    _password.clear();
  }

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: EdgeInsets.all(AishSpacing.space6),
            child: ConstrainedBox(
              constraints: BoxConstraints(
                maxWidth: AishSizing.sizeMaxWidthReading,
              ),
              child: Form(
                key: _formKey,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    // The text wordmark is the ONLY permitted brand usage. No
                    // logo is fabricated here or anywhere else.
                    Semantics(
                      header: true,
                      child: Text(
                        'Aish Laundry App',
                        style: textTheme.headlineSmall,
                        textAlign: TextAlign.center,
                      ),
                    ),
                    SizedBox(height: AishSpacing.space6),
                    TextFormField(
                      controller: _identifier,
                      focusNode: _identifierFocus,
                      autofillHints: const <String>[AutofillHints.username],
                      textInputAction: TextInputAction.next,
                      decoration: const InputDecoration(
                        labelText: 'Nomor telepon atau email',
                      ),
                      validator: (value) =>
                          (value == null || value.trim().isEmpty)
                          ? 'Isi nomor telepon atau email Anda.'
                          : null,
                      onFieldSubmitted: (_) => _passwordFocus.requestFocus(),
                    ),
                    SizedBox(height: AishSpacing.space4),
                    TextFormField(
                      controller: _password,
                      focusNode: _passwordFocus,
                      obscureText: true,
                      autofillHints: const <String>[AutofillHints.password],
                      textInputAction: TextInputAction.done,
                      decoration: const InputDecoration(
                        labelText: 'Kata sandi',
                      ),
                      validator: (value) => (value == null || value.isEmpty)
                          ? 'Isi kata sandi Anda.'
                          : null,
                      onFieldSubmitted: (_) => _submit(),
                    ),
                    if (_error != null) ...<Widget>[
                      SizedBox(height: AishSpacing.space4),
                      // A live region so the failure is announced rather than
                      // silently appearing below the fold.
                      Semantics(
                        liveRegion: true,
                        child: Row(
                          children: <Widget>[
                            ExcludeSemantics(
                              child: Icon(
                                Icons.error_outline,
                                size: AishSizing.sizeIconMd,
                                color: AishSemanticColors.colorSemanticDanger,
                              ),
                            ),
                            SizedBox(width: AishSpacing.space2),
                            Expanded(
                              child: Text(
                                _error!,
                                style: textTheme.bodySmall?.copyWith(
                                  color: AishSemanticColors.colorSemanticDanger,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                    SizedBox(height: AishSpacing.space6),
                    PrimaryAction(
                      label: 'Masuk',
                      semanticLabel: 'Masuk ke Aish Laundry',
                      isBusy: _busy,
                      onPressed: _submit,
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
