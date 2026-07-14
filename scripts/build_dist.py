# -*- coding: utf-8 -*-
"""Gera a pasta dist/ pronta para upload na Hostinger (public_html)."""
from __future__ import annotations

import os
import shutil
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DIST = os.path.join(ROOT, "dist")

COPY_FILES = (
    "index.html",
    ".htaccess",
    "robots.txt",
    "sitemap.xml",
)

COPY_DIRS = (
    "css",
    "js",
    "assets",
    "php",
    "admin",
)

COPY_DATOS_HTACCESS = True


def log(message: str) -> None:
    print(message)


def remove_dist() -> None:
    if os.path.isdir(DIST):
        shutil.rmtree(DIST)
        log(f"Removido: {DIST}")


def copy_tree(src_name: str) -> None:
    src = os.path.join(ROOT, src_name)
    dst = os.path.join(DIST, src_name)
    if not os.path.isdir(src):
        raise FileNotFoundError(f"Pasta obrigatoria ausente: {src}")
    shutil.copytree(src, dst)
    log(f"Copiado: {src_name}/")


def copy_file(name: str) -> None:
    src = os.path.join(ROOT, name)
    dst = os.path.join(DIST, name)
    if not os.path.isfile(src):
        raise FileNotFoundError(f"Arquivo obrigatorio ausente: {src}")
    os.makedirs(os.path.dirname(dst), exist_ok=True)
    shutil.copy2(src, dst)
    log(f"Copiado: {name}")


def prepare_dados_dir() -> None:
    dados_dir = os.path.join(DIST, "dados")
    os.makedirs(dados_dir, exist_ok=True)
    os.makedirs(os.path.join(dados_dir, "analytics"), exist_ok=True)

    src_htaccess = os.path.join(ROOT, "dados", ".htaccess")
    if COPY_DATOS_HTACCESS and os.path.isfile(src_htaccess):
        shutil.copy2(src_htaccess, os.path.join(dados_dir, ".htaccess"))
        log("Copiado: dados/.htaccess")

    keep = os.path.join(dados_dir, ".gitkeep")
    with open(keep, "w", encoding="utf-8") as handle:
        handle.write("")
    log("Criado: dados/.gitkeep")
    keep_analytics = os.path.join(dados_dir, "analytics", ".gitkeep")
    with open(keep_analytics, "w", encoding="utf-8") as handle:
        handle.write("")
    log("Criado: dados/analytics/.gitkeep")

    example_src = os.path.join(ROOT, "dados", "ga-service-account.example.json")
    if os.path.isfile(example_src):
        shutil.copy2(example_src, os.path.join(dados_dir, "ga-service-account.example.json"))
        log("Copiado: dados/ga-service-account.example.json")


def count_files(path: str) -> int:
    total = 0
    for _, _, files in os.walk(path):
        total += len(files)
    return total


def build() -> None:
    log(f"Build Hostinger -> {DIST}")
    remove_dist()
    os.makedirs(DIST, exist_ok=True)

    for file_name in COPY_FILES:
        copy_file(file_name)

    for dir_name in COPY_DIRS:
        copy_tree(dir_name)

    prepare_dados_dir()

    total = count_files(DIST)
    size_mb = sum(
        os.path.getsize(os.path.join(base, name))
        for base, _, names in os.walk(DIST)
        for name in names
    ) / (1024 * 1024)

    log("")
    log("Build concluido.")
    log(f"  Arquivos: {total}")
    log(f"  Tamanho:  {size_mb:.2f} MB")
    log("")
    log("Envie o conteudo de dist/ para public_html na Hostinger.")


if __name__ == "__main__":
    try:
        build()
    except Exception as exc:
        print(f"Erro no build: {exc}", file=sys.stderr)
        sys.exit(1)
