# -*- coding: utf-8 -*-
"""Build languages/barbas-connect-pt_BR.po + .mo (UTF-8, English msgids)."""
from datetime import datetime, timezone
from pathlib import Path

import polib

PAIRS = [
    (
        "Barbas Connect",
        "Barbas Connect",
    ),
    (
        "Site agent for Barbas Central: secure REST API, pairing keys, and bridge stubs for Activity Reports.",
        "Agente do site para o Barbas Central: API REST segura, chaves de pareamento e stubs de ponte para Activity Reports.",
    ),
    (
        "Missing files in includes/ or lib/. Delete the plugin folder and install via Plugins \u2192 Add New \u2192 Upload Plugin (barbas-connect.zip).",
        "Arquivos em includes/ ou lib/ ausentes. Apague a pasta do plugin e instale via Plugins \u2192 Adicionar novo \u2192 Enviar plugin (barbas-connect.zip).",
    ),
    ("Copied!", "Copiado!"),
    (
        "Could not copy. Select the key and copy manually.",
        "N\u00e3o foi poss\u00edvel copiar. Selecione a chave e copie manualmente.",
    ),
    ("Connections", "Conex\u00f5es"),
    ("License", "Licen\u00e7a"),
    (
        "You do not have permission to manage connections.",
        "Voc\u00ea n\u00e3o tem permiss\u00e3o para gerenciar conex\u00f5es.",
    ),
    ("Connected", "Conectado"),
    ("Pending pairing", "Pareamento pendente"),
    ("Unknown", "Desconhecido"),
    ("Barbas Digital", "Barbas Digital"),
    (
        "Connect this site to Barbas Central. Generate a pairing key, paste it in Barbas Central, then manage or revoke connections here.",
        "Conecte este site ao Barbas Central. Gere uma chave de pareamento, cole no Barbas Central e gerencie ou revogue as conex\u00f5es aqui.",
    ),
    ("v%s", "v%s"),
    (
        "New pairing key created. Copy it now \u2014 it will not be shown again.",
        "Nova chave de pareamento criada. Copie agora \u2014 ela n\u00e3o ser\u00e1 exibida novamente.",
    ),
    (
        "Pairing key rotated. Copy the new key now.",
        "Chave de pareamento regenerada. Copie a nova chave agora.",
    ),
    ("Connection disconnected.", "Conex\u00e3o desconectada."),
    (
        "All connections disconnected.",
        "Todas as conex\u00f5es foram desconectadas.",
    ),
    ("Something went wrong.", "Algo deu errado."),
    ("Your pairing key", "Sua chave de pareamento"),
    (
        "Copy this key into Barbas Central. For security it is shown only once.",
        "Cole esta chave no Barbas Central. Por seguran\u00e7a, ela \u00e9 exibida apenas uma vez.",
    ),
    ("Copy", "Copiar"),
    ("Hide key", "Ocultar chave"),
    ("Site status", "Status do site"),
    ("Site URL", "URL do site"),
    ("Health endpoint", "Endpoint de sa\u00fade"),
    ("Activity Reports", "Activity Reports"),
    ("Available", "Dispon\u00edvel"),
    ("Not installed / inactive", "N\u00e3o instalado / inativo"),
    ("Connected to Central", "Conectado ao Central"),
    ("Yes", "Sim"),
    ("No", "N\u00e3o"),
    ("New connection", "Nova conex\u00e3o"),
    (
        "Creates a pairing key for Barbas Central.",
        "Cria uma chave de pareamento para o Barbas Central.",
    ),
    ("Label (optional)", "R\u00f3tulo (opcional)"),
    ("e.g. Production", "ex.: Produ\u00e7\u00e3o"),
    ("Generate pairing key", "Gerar chave de pareamento"),
    (
        "Disconnect all connections? Pairing keys will stop working.",
        "Desconectar todas as conex\u00f5es? As chaves de pareamento deixar\u00e3o de funcionar.",
    ),
    ("Disconnect all", "Desconectar todas"),
    (
        "No connections yet. Generate a pairing key to get started.",
        "Nenhuma conex\u00e3o ainda. Gere uma chave de pareamento para come\u00e7ar.",
    ),
    ("Label", "R\u00f3tulo"),
    ("Status", "Status"),
    ("Created", "Criada"),
    ("Last seen", "\u00daltima atividade"),
    ("Actions", "A\u00e7\u00f5es"),
    ("(no label)", "(sem r\u00f3tulo)"),
    ("Generate new key", "Gerar nova chave"),
    ("Disconnect this connection?", "Desconectar esta conex\u00e3o?"),
    ("Disconnect", "Desconectar"),
    (
        "Could not store the pairing key securely (OpenSSL required).",
        "N\u00e3o foi poss\u00edvel armazenar a chave de pareamento com seguran\u00e7a (OpenSSL necess\u00e1rio).",
    ),
    ("Could not save the connection.", "N\u00e3o foi poss\u00edvel salvar a conex\u00e3o."),
    ("Connection not found.", "Conex\u00e3o n\u00e3o encontrada."),
    (
        "This site already has a connection. Disconnect it before pairing with another Central.",
        "Este site j\u00e1 possui uma conex\u00e3o. Desconecte-a antes de parear com outro Central.",
    ),
    (
        "This site is already paired with Barbas Central. Disconnect before pairing again.",
        "Este site j\u00e1 est\u00e1 pareado com o Barbas Central. Desconecte antes de parear novamente.",
    ),
    ("Invalid pairing key.", "Chave de pareamento inv\u00e1lida."),
    (
        "No pending connection matches this pairing key.",
        "Nenhuma conex\u00e3o pendente corresponde a esta chave de pareamento.",
    ),
    ("Pairing failed.", "Falha no pareamento."),
    (
        "Missing Barbas Connect authentication headers.",
        "Cabe\u00e7alhos de autentica\u00e7\u00e3o do Barbas Connect ausentes.",
    ),
    ("Invalid timestamp.", "Timestamp inv\u00e1lido."),
    (
        "Request timestamp outside allowed window.",
        "Timestamp da solicita\u00e7\u00e3o fora da janela permitida.",
    ),
    ("Invalid nonce.", "Nonce inv\u00e1lido."),
    ("Nonce already used.", "Nonce j\u00e1 utilizado."),
    ("Unknown connection.", "Conex\u00e3o desconhecida."),
    ("Invalid signature.", "Assinatura inv\u00e1lida."),
    ("Connect", "Connect"),
    (
        'Could not check for updates. Configure the license under <a href="%s">Barbas Update \u2192 Connect</a> (private repository).',
        'N\u00e3o foi poss\u00edvel verificar atualiza\u00e7\u00f5es. Configure a licen\u00e7a em <a href="%s">Barbas Update \u2192 Connect</a> (reposit\u00f3rio privado).',
    ),
]


def main() -> None:
    now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M%z")
    po = polib.POFile()
    po.metadata = {
        "Project-Id-Version": "Barbas Connect 0.1.10",
        "Report-Msgid-Bugs-To": "https://www.barbas.digital",
        "POT-Creation-Date": now,
        "PO-Revision-Date": now,
        "Last-Translator": "Barbas Digital",
        "Language-Team": "Portugu\u00eas do Brasil",
        "Language": "pt_BR",
        "MIME-Version": "1.0",
        "Content-Type": "text/plain; charset=UTF-8",
        "Content-Transfer-Encoding": "8bit",
        "Plural-Forms": "nplurals=2; plural=(n != 1);",
        "X-Domain": "barbas-connect",
    }

    seen = set()
    for msgid, msgstr in PAIRS:
        if msgid in seen:
            continue
        seen.add(msgid)
        po.append(polib.POEntry(msgid=msgid, msgstr=msgstr))

    lang = Path(__file__).resolve().parents[1] / "languages"
    po_path = lang / "barbas-connect-pt_BR.po"
    mo_path = lang / "barbas-connect-pt_BR.mo"
    po.save(str(po_path))
    po.save_as_mofile(str(mo_path))

    loaded = polib.mofile(str(mo_path))
    assert loaded.find("Generate pairing key").msgstr == "Gerar chave de pareamento"
    print("wrote", po_path, "entries", len(po))
    print("wrote", mo_path, "bytes", mo_path.stat().st_size)


if __name__ == "__main__":
    main()
