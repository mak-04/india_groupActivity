"""extract_text.py — extract plain text from PDF, DOCX, or TXT files."""
import sys
import zipfile
import xml.etree.ElementTree as ET
from pathlib import Path


def extract_txt(path: Path) -> str:
    return path.read_text(encoding="utf-8", errors="ignore")


def extract_docx(path: Path) -> str:
    with zipfile.ZipFile(path) as docx:
        xml_bytes = docx.read("word/document.xml")
    root = ET.fromstring(xml_bytes)
    ns   = {"w": "http://schemas.openxmlformats.org/wordprocessingml/2006/main"}
    return "\n".join(node.text for node in root.findall(".//w:t", ns) if node.text)


def extract_pdf(path: Path) -> str:
    try:
        from pypdf import PdfReader
    except ImportError:
        try:
            from PyPDF2 import PdfReader
        except ImportError:
            return "PDF extraction requires pypdf. Install with: pip install pypdf"
    reader = PdfReader(str(path))
    return "\n".join(page.extract_text() or "" for page in reader.pages)


def main() -> int:
    if len(sys.argv) < 2:
        print("")
        return 0

    path = Path(sys.argv[1])
    if not path.exists():
        print(f"File not found: {path}", file=sys.stderr)
        return 1

    ext = path.suffix.lower()
    if ext == ".txt":
        text = extract_txt(path)
    elif ext == ".docx":
        text = extract_docx(path)
    elif ext == ".pdf":
        text = extract_pdf(path)
    else:
        text = ""

    # Limit to 30 000 chars to avoid overloading Gemini prompt
    print(text[:30000])
    return 0


if __name__ == "__main__":
    raise SystemExit(main())