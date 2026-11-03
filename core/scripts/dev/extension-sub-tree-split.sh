#!/bin/bash
#
# This script creates the sub-tree split when an extension is removed from core.
#
# @internal
#   This script is not covered by Drupal core's backwards compatibility promise.
#   It exists only for core development purposes.
#

# Working directory.
WORKING_DIR=/tmp/split

while test $# -gt 0; do
  case "$1" in
    -h|--help)
      echo "Create a sub-tree split for extension removal."
      echo " "
      echo "You need the commit hash for where to make the split."
      echo "Use 'git log -- modules/MODULE_NAME' or 'git log -- themes/THEME_NAME' and save the commit hash."
      echo " "
      echo "options:"
      echo "-h, --help                show brief help"
      echo " "
      echo "Example usage: sh ./core/scripts/dev/extension-sub-tree-split"
      exit 0
      ;;
    *)
      break
      ;;
  esac
done

# Get user input for the extension name and type.
echo -e "\nEnter the extension name: "
read EXTENSION

TYPE=modules
if [ -d "./core/themes/$EXTENSION" ]
then
  TYPE=themes
  if [ -d "./core/modules/$EXTENSION" ]
  then
    echo -e "Is this a module (Y/n)?:"
    read ANSWER
    if [ -z "$ANSWER" ]
    then
      TYPE=modules
    fi
  fi
fi

# Ask for a user-specified hash.
echo -e "Enter a commit hash: "
read HASH
if [ -n "$HASH" ]
then
  COMMIT_HASH=$HASH
fi

# Start the sub-tree split process.
echo "Executing a sub-tree split for $TYPE/$EXTENSION."

# 1. Create a working directory and clone Drupal.
mkdir "$WORKING_DIR"
cd "$WORKING_DIR" || exit 1
if ! git clone https://git.drupalcode.org/project/drupal.git
then
  printf "\ngit clone failed."
  exit 1
fi

cd drupal || exit 1

# 2. Split core/extension_type/extension.
if ! git subtree split -P core/"$TYPE"/"$EXTENSION" -b "$EXTENSION"
then
  printf "\First subtree split failed."
  exit 1
fi

# 3. Split core/extension_type/extension and merge it with the history for
#    core/core/extension_type/extension.
#    - Find the commit where the extension was moved to
#      core/extension_type/extension.
#    - If there is no output, SKIP TO STEP 4. Otherwise, note the SHA of the
#      most recent commit.
git tag core-move "$COMMIT_HASH"
git checkout core-move
if ! git subtree split -P core/"$TYPE"/"$EXTENSION" -b "$EXTENSION"-pre-core
then
  printf "\Second sub-tree split failed"
  exit 1
fi
git checkout "$EXTENSION"

# Rebase the post-core-move branch onto the pre-core move. Note
# that we don't want the actual commit that did the move: it has
# no effect in our split since the module is top-level in both the
# new branches.
if ! git rebase --onto "$EXTENSION"-pre-core core-move
then
  printf "\Rebase failed"
  exit 1
fi

# 4.Create the new repository from the sub-tree split.
git checkout "$EXTENSION"
# Create the repository in a new directory.
cd ..
mkdir "$EXTENSION"
cd "$EXTENSION" || exit 1
git init
git pull ../drupal "$EXTENSION"
git remote add origin https://git.drupalcode.org/project/"$EXTENSION".git

echo -e "The split is at $WORKING_DIR/$EXTENSION."


