# A Telegram Bot

一个多功能Telegram机器人

## 获取用户DC

命令格式：`/dc [user]`

返回指定用户的DC信息，不指定user时返回自身DC信息

查询目标用户必须有用户名，并且设置了所有人可见的头像

## IP信息查询

命令格式：`/ip IPv4/IPv6/Domain`

输入IPv4或IPv6时返回指定IP地址的详细信息

输入域名时解析域名的A记录与AAAA记录，以列表形式返回，可选其中任意IP地址查询

## CFOP公式图片获取

命令格式：`/cfop [gan/mfg/yx]`

返回CFOP公式图片，可选GAN、魔方格、裕鑫三套公式

仅输入CFOP命令时，可在返回选项里自行选定版本

## KMS服务器检测

命令格式：`/kms host[:port]`

其中host为KMS服务器IP/域名，port默认为1688端口，命令查询指定服务器是否正常工作

同时该命令提供了KMS密钥查询功能，可获取不同版本Windows/Windows Server的密钥

## NTP服务器检测

命令格式：`/ntp host`

其中host为NTP服务器IP/域名，查询目标服务是否正常工作

返回数据包括host下的所有可用服务器地址、级别、相对偏移等信息

同时该命令也提供了目前全球常用NTP服务器列表

## Punycode编码转换

命令格式：`/punyc content` 或 `/punycode content`

content内容可为明文或编码后的punycode，bot会自动识别并进行转换

## TLD信息查询

命令格式：`/tld top-level-domain`

查询顶级域名信息，返回数据包括类型、管理者、联系人、NS服务器、DNSSEC、ICP管理、日期等信息

## ICP备案查询

命令格式：`/icp domain`

查询域名ICP备案情况，返回域名所有者、备案内容、备案时间等信息
