Dell Inspiron 17 7779 battery connection check
================================================

Your 65W cord is the adapter this laptop was built for.
Dell rates the Inspiron 17 7779 (P30E) adapter at 65W, 19.5V, 3.34A.
The battery is a 4-cell 56Wh pack at 15.2V.

This check asks the motherboard whether that cord is really charging
the pack. It then writes a green-signal board on your Desktop.

What each green signal means
----------------------------
- Cord: the motherboard sees power from the 65W cord.
- 65W ID pin: the center pin in the round plug is being read as 65W.
- Barrel fitting: AC stayed connected across repeated reads.
- Motherboard: the embedded controller answered.
- Battery cable: Windows can see the pack.
- Charge current: the pack is taking current, or it is already full.
- Driver refresh: the Windows ACPI battery driver was cycled while AC was steady.

A red signal names the link that failed. A 65W label on the brick is
not enough. A generic cord often runs the laptop and still will not
charge, because the motherboard never reads the center pin. Dell shows
that as "AC adapter wattage cannot be determined."

How to run it
-------------
1. Plug the 65W cord into the wall and fully into the laptop.
2. Leave it plugged in. Do not run this on battery only.
3. Open the zip and double-click Run-Battery-Connection-Check.cmd.
   Windows can run that one file directly. You do not have to copy the
   other files out first.
4. Approve the administrator prompt.
5. Read the green and red lines. The same board opens in your browser
   and is saved on your Desktop in a folder named
   Inspiron-7779-Battery-<date>.

The software fix
----------------
While the motherboard reports steady AC power, the script disables and
re-enables only the Microsoft ACPI battery device, then scans hardware
again. That clears a stuck Windows battery driver.

It does not open the case, change BIOS settings, or replace parts.
If the cord signal stays red after a firm plug-in, the power-adapter
port on this model is a separate plug on the motherboard. Dell's
service manual for the Inspiron 17 7779 covers that port:

https://www.dell.com/support/manuals/en-us/inspiron-17-7779-2-in-1-laptop/inspiron-17-7779-laptop-sm/

Unplug the cord before any hardware work. Do not open the laptop while
the 65W cord is connected.
