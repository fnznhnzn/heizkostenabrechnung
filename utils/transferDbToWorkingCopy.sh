#!/bin/bash
# just don't ever run this on sirup
ssh -p 2200 sirup.km-it.de "mysqldump -C heizkostenabrechnung -u root -pK-NM780" | mysql heizkostenabrechnung -u root -pK-NM780