import 'package:flutter_riverpod/flutter_riverpod.dart';

/// A picked defect photo — raw bytes plus a filename for the multipart upload.
final class PickedPhoto {
  const PickedPhoto({required this.bytes, required this.filename});

  final List<int> bytes;
  final String filename;
}

/// The source of a QC defect photo (camera or gallery). An INJECTED SEAM so the
/// durable upload path can be exercised end-to-end from fixtures WITHOUT a device
/// or emulator (owner constraint: do not fabricate physical-camera evidence;
/// classify truthfully). A device build wires a real picker behind this
/// interface; a test injects a fake that returns known bytes.
abstract interface class PhotoSource {
  /// Returns the picked photo, or null if the user cancels or capture is not
  /// available on this build.
  Future<PickedPhoto?> pick();
}

/// The default: capture is NOT wired to a camera/gallery plugin in this
/// foundation build, so it honestly reports "unavailable" rather than pretending
/// to capture. It never returns fabricated bytes. A real
/// image-picker-backed source is the device implementation of this seam.
final class UnavailablePhotoSource implements PhotoSource {
  const UnavailablePhotoSource();

  @override
  Future<PickedPhoto?> pick() async => null;
}

/// The active photo source. Overridden in tests with a fake; a device build
/// overrides it with a real camera/gallery-backed implementation.
final Provider<PhotoSource> photoSourceProvider = Provider<PhotoSource>(
  (ref) => const UnavailablePhotoSource(),
);
