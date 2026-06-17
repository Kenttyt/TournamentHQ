@echo off
if not exist "C:\xampp\htdocs\TournamentHQ\var" mkdir "C:\xampp\htdocs\TournamentHQ\var"
"C:\xampp\php\php.exe" "C:\xampp\htdocs\TournamentHQ\scripts\cleanup_proofs.php" >> "C:\xampp\htdocs\TournamentHQ\var\cleanup_proofs.log" 2>&1
