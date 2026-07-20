# Claude Code Master Prompt Modes

Simpan folder ini di repository aplikasi, misalnya:

`docs/governance/claude-code/`

Gunakan:

- `01-CLAUDE-CODE-MASTER-START-MODE.md` ketika sprint belum dimulai.
- `02-CLAUDE-CODE-MASTER-RESUME-MODE.md` ketika sprint sedang berjalan.
- `03-CLAUDE-CODE-MASTER-CLOSURE-MODE.md` ketika sprint hampir selesai.

Cara pakai:

1. Buka file mode yang sesuai.
2. Tambahkan konteks sprint aktif di bagian awal atau kirim bersama instruksi sprint.
3. Tempel seluruh prompt ke Claude Code.
4. Pastikan Claude memverifikasi repository, bukan mengandalkan status lama.
5. Simpan checkpoint per phase bila sesi panjang.

Rekomendasi:
- Jadikan ketiga file sebagai protected governance artifacts.
- Perubahan terhadap prompt harus melalui PR dan decision record.
- Jangan menyalin detail sprint permanen ke file master; simpan detail sprint di prompt eksekusi atau sprint brief terpisah.
