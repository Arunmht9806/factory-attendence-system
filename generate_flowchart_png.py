from PIL import Image, ImageDraw, ImageFont

W, H = 2200, 3400
bg = (248, 250, 252)
img = Image.new("RGB", (W, H), bg)
draw = ImageDraw.Draw(img)

# Fonts
try:
    title_font = ImageFont.truetype("arial.ttf", 56)
    section_font = ImageFont.truetype("arial.ttf", 34)
    text_font = ImageFont.truetype("arial.ttf", 24)
except Exception:
    title_font = ImageFont.load_default()
    section_font = ImageFont.load_default()
    text_font = ImageFont.load_default()

# Helpers

def rounded_box(x1, y1, x2, y2, fill, outline=(30, 41, 59), width=3, r=22):
    draw.rounded_rectangle([x1, y1, x2, y2], radius=r, fill=fill, outline=outline, width=width)


def multiline_text(x, y, text, font, fill=(15, 23, 42), max_width=500, line_gap=7):
    words = text.split()
    lines = []
    line = ""
    for w in words:
        test = (line + " " + w).strip()
        bbox = draw.textbbox((0, 0), test, font=font)
        if bbox[2] - bbox[0] <= max_width:
            line = test
        else:
            if line:
                lines.append(line)
            line = w
    if line:
        lines.append(line)

    yy = y
    for ln in lines:
        draw.text((x, yy), ln, font=font, fill=fill)
        h = draw.textbbox((0, 0), ln, font=font)[3]
        yy += h + line_gap


def arrow_down(cx, y1, y2, color=(30, 41, 59), width=4):
    draw.line((cx, y1, cx, y2), fill=color, width=width)
    draw.polygon([(cx - 10, y2 - 16), (cx + 10, y2 - 16), (cx, y2)], fill=color)


def arrow_right(x1, y, x2, color=(30, 41, 59), width=4):
    draw.line((x1, y, x2, y), fill=color, width=width)
    draw.polygon([(x2 - 16, y - 10), (x2 - 16, y + 10), (x2, y)], fill=color)

# Title
rounded_box(80, 40, W - 80, 150, fill=(30, 64, 175), outline=(30, 64, 175), r=18)
draw.text((110, 72), "Factory Attendance System - Complete Working Flow", font=title_font, fill=(255, 255, 255))

# Main vertical chain (core flow)
cx = 360
box_w = 560
box_h = 140
x1 = cx - box_w // 2
x2 = cx + box_w // 2
start_y = 220
gap = 95

core_steps = [
    ("1. Login", "User ID/password -> validate -> session created"),
    ("2. Dashboard Init", "Load employees, holidays, users, attendance, vehicle usage"),
    ("3. Punch Terminal", "Regular punch or vehicle punch with live timestamp"),
    ("4. API Processing", "Validate inputs, save punches/sessions, apply limits"),
    ("5. Attendance Engine", "Build sessions, compute hours/OT/leave/day type"),
    ("6. Reports & Export", "Attendance sheet + Summary sheet in XLSX"),
]

ys = []
for i, (title, body) in enumerate(core_steps):
    y1 = start_y + i * (box_h + gap)
    y2 = y1 + box_h
    ys.append((y1, y2))
    rounded_box(x1, y1, x2, y2, fill=(219, 234, 254))
    draw.text((x1 + 22, y1 + 18), title, font=section_font, fill=(30, 58, 138))
    multiline_text(x1 + 22, y1 + 64, body, text_font, max_width=box_w - 44)
    if i < len(core_steps) - 1:
        arrow_down(cx, y2, y2 + gap - 20)

# Side branches
# Authentication detail
sx1, sx2 = 780, 2100
sy1, sy2 = 220, 520
rounded_box(sx1, sy1, sx2, sy2, fill=(236, 253, 245), outline=(22, 101, 52))
draw.text((sx1 + 20, sy1 + 14), "Authentication & Access Control", font=section_font, fill=(22, 101, 52))
multiline_text(
    sx1 + 20,
    sy1 + 66,
    "- users table auto-ensured\n"
    "- Default admin seeded when empty\n"
    "- Roles: admin, hr, viewer\n"
    "- Protected API requires active session\n"
    "- Admin/HR can manage users and sensitive actions",
    text_font,
    max_width=(sx2 - sx1 - 40),
)
arrow_right(x2 + 10, (ys[0][0] + ys[0][1]) // 2, sx1 - 12)

# Punch details
sy1, sy2 = 620, 980
rounded_box(sx1, sy1, sx2, sy2, fill=(254, 249, 195), outline=(133, 77, 14))
draw.text((sx1 + 20, sy1 + 14), "Punch Terminal Logic", font=section_font, fill=(133, 77, 14))
multiline_text(
    sx1 + 20,
    sy1 + 66,
    "- Department + employee selection\n"
    "- Regular punch stores attendance timestamp\n"
    "- Vehicle punch starts/ends session via token\n"
    "- Max 6 sessions/day for regular attendance",
    text_font,
    max_width=(sx2 - sx1 - 40),
)
arrow_right(x2 + 10, (ys[2][0] + ys[2][1]) // 2, sx1 - 12)

# Attendance computation details
sy1, sy2 = 1080, 1550
rounded_box(sx1, sy1, sx2, sy2, fill=(243, 232, 255), outline=(107, 33, 168))
draw.text((sx1 + 20, sy1 + 14), "Attendance Calculation Rules", font=section_font, fill=(107, 33, 168))
multiline_text(
    sx1 + 20,
    sy1 + 66,
    "- Pair in/out timestamps into sessions\n"
    "- totalHours from valid session pairs\n"
    "- Weekday: regular up to 8h, extra as OT\n"
    "- Saturday/Public Holiday: all hours as OT\n"
    "- Leave on weekday:\n"
    "  * 0h -> Full Leave (1.0)\n"
    "  * >0h and <=5h -> Half Leave (0.5)\n"
    "  * >5h -> Present",
    text_font,
    max_width=(sx2 - sx1 - 40),
)
arrow_right(x2 + 10, (ys[4][0] + ys[4][1]) // 2, sx1 - 12)

# Admin modules
sx1b, sx2b = 780, 2100
sy1, sy2 = 1650, 2140
rounded_box(sx1b, sy1, sx2b, sy2, fill=(224, 242, 254), outline=(3, 105, 161))
draw.text((sx1b + 20, sy1 + 14), "Admin/HR Modules", font=section_font, fill=(3, 105, 161))
multiline_text(
    sx1b + 20,
    sy1 + 66,
    "- Staff CRUD + HR attendance-edit permission\n"
    "- Login User CRUD (admin/hr/viewer)\n"
    "- Public Holiday CRUD\n"
    "- Vehicle session edit/delete with duration\n"
    "- Bulk staff import from XLSX template",
    text_font,
    max_width=(sx2b - sx1b - 40),
)
arrow_right(x2 + 10, (ys[5][0] + ys[5][1]) // 2, sx1b - 12)

# Export details box
ex1, ex2 = 130, 2320
ey1, ey2 = 2360, 3190
rounded_box(ex1, ey1, ex2, ey2, fill=(241, 245, 249), outline=(51, 65, 85))
draw.text((ex1 + 20, ey1 + 14), "Export Output (MS Excel)", font=section_font, fill=(51, 65, 85))
multiline_text(
    ex1 + 20,
    ey1 + 72,
    "Sheet 1: Attendance\n"
    "- Employee/day-wise rows\n"
    "- Day type, session time, leave type/days\n"
    "- Regular hours, OT hours, total hours\n\n"
    "Sheet 2: Summary (per employee)\n"
    "- Total Days\n"
    "- Weekend Days\n"
    "- Public Holiday Days\n"
    "- Present Days\n"
    "- Leave Days\n"
    "- Half Leave / Full Leave\n"
    "- Regular Hours\n"
    "- OT Hours\n"
    "- Worked Hours\n\n"
    "System safeguards:\n"
    "- Clean binary output for downloads\n"
    "- ZipArchive-based XLSX package when available",
    text_font,
    max_width=(ex2 - ex1 - 40),
)
arrow_down(cx, ys[-1][1], ey1 - 20)

# Footer
draw.text((80, H - 52), "Generated diagram: factory_attendance_system_flowchart.png", font=text_font, fill=(100, 116, 139))

img.save("factory_attendance_system_flowchart.png", "PNG")
print("Created: factory_attendance_system_flowchart.png")
