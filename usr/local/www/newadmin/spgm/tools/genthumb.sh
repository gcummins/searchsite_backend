#!/bin/bash
#
# thumbnails_creation.sh
#
# automatic thumbnails creation script
#
#########################################



##################################
## Global variables
##################################
dir_default="."
size_default="r:20"
prefix_default="_thb_"

cmd=`basename ${0}`
usage_string="Usage: ${cmd} [options...] [DIRS...]
DIRS: directories to process (default: current directory)

Options:
    -s, --size <SIZE>	size of generated thumbnails (default: r:20)
    -R			recursive mode (default: no)
    -p, --prefix <PREF> thumnails prefix (default: _thb_)
    -h, --help		display this help and exit

Example:
    thumbnails_creation.sh --prefix _thumb_ --size r:25 -R dir/"

error=1
error_message() {
    echo "${1}" >&2
    exit ${error}
}
usage() {
    error_message "${usage_string}"
}




##################################
## Parameters retreiving
##################################
while true; do
    case "x${1}" in
	"x-s"|"x--size")
	    shift; [ -z "${1}" ] && usage;
	    size="${1}";;
	"x-R")
	    rec="1";;
	"x-p"|"x--prefix")
	    shift; [ -z "${1}" ] && usage;
	    prefix="${1}";;
	"x")
	    break;;
	"x-h"|"x--help")
	    usage;;
	*)
	    [[ ${1} == -* ]] && usage;
	    dirs="$dirs ${1}";;
    esac
    shift;
done

[ -z "${dirs}" ] && dirs="${dir_default}"
[ -z "${size}" ] && size="${size_default}"
[ -z "${prefix}" ] && prefix="${prefix_default}"

[[ ${size} == h:* ]] && geometry="x${size#h:}"
[[ ${size} == w:* ]] && geometry="${size#w:}"
[[ ${size} == r:* ]] && geometry="${size#r:}%"



#####################################
## Main loop
#####################################
for dir in ${dirs}; do
    if [ ! -d ${dir} ]; then
	echo "directory ${dir} does not exist... skipped."
	continue
    fi
    for file in ${dir}/*; do
	if [ -d ${file} ]; then
	    if [ -n "${rec}" ]; then
		"${0}" -s "${size}" -p "${prefix}" -R ${file}
	    fi
	else
	    file "${file}" | grep -i "image" > /dev/null
	    if [ $? -eq 0 ]; then
		filename=`basename ${file}`
		if [[ ${filename} != ${prefix}* ]]; then
		    convert -geometry "${geometry}" "${file}" "${dir}/${prefix}${filename}"
		fi
	    fi
	fi
    done
done
