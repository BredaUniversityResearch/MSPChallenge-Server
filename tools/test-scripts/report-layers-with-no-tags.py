import os
import json

os.chdir(os.path.join(os.path.dirname(__file__), '../../'))

# Step 1: List all directories in ServerManager/configfiles/
base_path = 'ServerManager/configfiles/'
directories = [d for d in os.listdir(base_path) if os.path.isdir(os.path.join(base_path, d))]

if not directories:
    print("No directories found in 'ServerManager/configfiles/'.")
    exit()

print("** Print all layers without tags for a given .json config file **")
print("Available directories:")
for i, directory in enumerate(directories, start=1):
    print(f"{i}. {directory}")

# Step 2: Ask the user to select a directory
dir_choice = int(input("Select a directory by number: ")) - 1
if dir_choice < 0 or dir_choice >= len(directories):
    print("Invalid choice.")
    exit()

selected_directory = os.path.join(base_path, directories[dir_choice])

# Step 3: List all .json files in the selected directory
json_files = [f for f in os.listdir(selected_directory) if f.endswith('.json')]

if not json_files:
    print(f"No .json files found in '{selected_directory}'.")
    exit()

# If only one file exists, automatically select it
if len(json_files) == 1:
    selected_file = os.path.join(selected_directory, json_files[0])
    print(f"Only one file found: {json_files[0]}. Automatically selecting it.")
else:
    print("Available .json files:")
    for i, json_file in enumerate(json_files, start=1):
        print(f"{i}. {json_file}")

    # Step 4: Ask the user to select a .json file
    file_choice = int(input("Select a .json file by number: ")) - 1
    if file_choice < 0 or file_choice >= len(json_files):
        print("Invalid choice.")
        exit()

    selected_file = os.path.join(selected_directory, json_files[file_choice])

# Step 5: Run the script with the selected .json file
with open(selected_file, 'r') as file:
    data = json.load(file)

# Find all "meta" objects without the "layer_tags" field or with an empty "layer_tags"
meta_items_without_layer_tags = []
for meta_item in data.get("datamodel", {}).get("meta", []):
    if "layer_tags" not in meta_item or not meta_item["layer_tags"]:
        meta_items_without_layer_tags.append(meta_item.get("layer_short", "Unknown"))

# Report the results
print(f"Found {len(meta_items_without_layer_tags)} 'meta' items without 'layer_tags' or with empty 'layer_tags':")
for layer_short in meta_items_without_layer_tags:
    print(layer_short)