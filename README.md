<!-- README ini dihasilkan otomatis oleh .github/workflows/generate-readme.yml -->
<!-- Jangan edit manual: perubahan akan ditimpa pada run berikutnya. -->

<h1 align="center">Sistem Absensi Face Recognition 👋</h1>

<p align="center">
  <a href="https://github.com/antono4/face-attendance"><img alt="GitHub repo" src="https://img.shields.io/badge/GitHub-antono4/face-attendance-blue?logo=github"></a>
  <a href="https://antono4.github.io/face-attendance/"><img alt="Live Demo" src="https://img.shields.io/badge/Live%20Demo-Online-success?logo=githubpages"></a>
  <img alt="Files" src="https://img.shields.io/badge/Files-15-informational">
  <img alt="Last commit" src="https://img.shields.io/github/last-commit/antono4/face-attendance">
</p>

---

## 📖 Tentang

Repository **`face-attendance`** adalah situs statis yang dibangun dengan HTML, PHP.
Situs ini diterbitkan melalui **GitHub Pages** dan dapat diakses di [`https://antono4.github.io/face-attendance/`](https://antono4.github.io/face-attendance/).

## 🗂️ Struktur Proyek

```
face-attendance/
.github/
  workflows/
LICENSE
api.php
index.html
models/
  face_landmark_68_model-weights_blob
  face_landmark_68_model-weights_manifest.json
  face_recognition_model-weights_blob
  face_recognition_model-weights_blob2
  face_recognition_model-weights_manifest.json
  ssd_mobilenetv1_model-weights_blob
  ssd_mobilenetv1_model-weights_blob2
  ssd_mobilenetv1_model-weights_manifest.json
  tiny_face_detector_model-weights_blob
  tiny_face_detector_model-weights_manifest.json
schema.sql
```

## 🛠️ Teknologi

Berdasarkan ekstensi berkas yang terdeteksi di repository:

- `HTML`
- `PHP`

> Total **15 berkas** di repository (di luar `.git`, `node_modules`, `dist`, dan `build`).

## 🚀 Menjalankan Secara Lokal

Tanpa dependency apa pun. Buka `index.html` langsung di browser, atau jalankan server statis:

```bash
python3 -m http.server 8000
# lalu buka http://localhost:8000
```

## 📬 Kontak

- GitHub: [antono4](https://github.com/antono4)

## 📄 Lisensi

Proyek ini dilisensikan di bawah MIT License — lihat berkas [`LICENSE`](./LICENSE).

---

<sub>README ini di-generate otomatis oleh GitHub Actions `.github/workflows/generate-readme.yml`.</sub>
