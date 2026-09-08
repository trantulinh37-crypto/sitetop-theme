#!/usr/bin/env python3
"""Kiểm cú pháp phần JS của widget.js.php TRƯỚC khi commit.
php -l chỉ kiểm PHP, không thấy lỗi JS — đó là cách top.js từng lọt lỗi ra production."""
import re, subprocess, sys, tempfile, os, io
src = io.open(sys.argv[1], encoding='utf-8').read()
i = src.find("(function(){'use strict';")
assert i != -1, "không tìm thấy đầu IIFE"
js = src[i:]
# Thay mọi khối PHP nhúng bằng chuỗi rỗng hợp lệ trong JS
# echo -> 0 (hợp lệ cả trong lẫn ngoài chuỗi); khối điều khiển (if/else/endif/foreach) -> xoá
js = re.sub(r"<\?php\s*(?:if|else|elseif|endif|foreach|endforeach|for|endfor|while|endwhile)\b.*?\?>", "", js, flags=re.S)
js = re.sub(r"<\?php.*?\?>", "0", js, flags=re.S)
js = re.sub(r"<\?=.*?\?>", "0", js, flags=re.S)
# Khối PHP cuối file mở mà KHÔNG đóng (chạy tới EOF) — .one có kiểu này. Regex trên đòi
# "?>" nên bỏ sót, để nguyên PHP lẫn vào JS làm node báo lỗi giả. Cắt từ đó tới hết.
con = js.find("<?php")
if con != -1: js = js[:con]
with tempfile.NamedTemporaryFile('w', suffix='.js', delete=False, encoding='utf-8') as f:
    f.write(js); tmp = f.name
r = subprocess.run(['node','--check',tmp], capture_output=True, text=True)
os.unlink(tmp)
if r.returncode == 0:
    print("✅ JS hợp lệ")
else:
    print("❌ JS LỖI CÚ PHÁP:\n" + (r.stderr or '')[:800]); sys.exit(1)
