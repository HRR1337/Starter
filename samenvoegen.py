import os

def verzamel_php_bestanden(input_directory, output_file):
    """
    Verzamelt alle PHP-bestanden in de opgegeven directory en subdirectories,
    en schrijft hun paden en inhoud naar een enkel uitvoerbestand.

    :param input_directory: De directory om te scannen.
    :param output_file: Het uitvoerbestand waarin alle content wordt opgeslagen.
    """
    with open(output_file, 'w', encoding='utf-8') as outfile:
        for root, dirs, files in os.walk(input_directory):
            for file in files:
                if file.endswith('.php'):
                    # Relatief pad ten opzichte van de input_directory
                    relative_path = os.path.relpath(os.path.join(root, file), input_directory)
                    
                    # Schrijf het relatieve pad en de scheidingslijn
                    outfile.write(f"{relative_path}\n\n---content---\n\n")
                    
                    # Lees en schrijf de inhoud van het PHP-bestand
                    file_path = os.path.join(root, file)
                    try:
                        with open(file_path, 'r', encoding='utf-8') as infile:
                            content = infile.read()
                            outfile.write(content + "\n\n")
                    except Exception as e:
                        print(f"Fout bij het lezen van {file_path}: {e}")
    
    print(f"Alle PHP-bestanden zijn succesvol verzameld in '{output_file}'.")

if __name__ == "__main__":
    import argparse

    # Stel argumenten in voor de command-line interface
    parser = argparse.ArgumentParser(description="Verzamel alle PHP-bestanden in één uitvoerbestand.")
    parser.add_argument('directory', nargs='?', default='.', 
                        help='De directory om te scannen (standaard: huidige directory).')
    parser.add_argument('-o', '--output', default='samengevoegd.php',
                        help='Naam van het uitvoerbestand (standaard: samengevoegd.php).')

    args = parser.parse_args()

    # Roep de functie aan met de opgegeven argumenten
    verzamel_php_bestanden(args.directory, args.output)