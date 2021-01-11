package common

import (
	"github.com/fsnotify/fsnotify"
	"github.com/spf13/viper"
	"time"
	"yuer/helper"
	"os"
)

const (
	CONF_PATH   = "/conf"  // 日志路径
	COMMON_CONF = "common" // 公共配置文件
	CONF_TYPE   = "json"   // 配置文件类型

	// 线上环境在k8s不区别预发布,与生产;需要配置自定义环境变量的区别
	ENV_TYPE_KEY = "THS_ENV_TYPE"
	ENV_DEV      = "dev"
	ENV_TEST     = "test"
	ENV_PREP     = "prep"
	ENV_PROD     = "prod"
)

var (
	EnvMap = map[string]string{"dev": ENV_DEV, "test": ENV_TEST, "prep": ENV_PREP, "prod": ENV_PROD}

	comonConfig  = viper.New()
	localConfig  = viper.New()

	// 系统环境
	env string
)

func init() {
	env = EnvMap[os.Getenv(ENV_TYPE_KEY)]
	loadCommonFileConfig(comonConfig)
	loadLocalFileConfig(localConfig)
}

//返回当前环境名称
func GetEnv() string {
	if env == "" {
		return ENV_DEV
	}
	return env
}

// 获取配置文件中的字符串配置项
func GetString(s string) string {
	return getConfigSource(s).GetString(s)
}

//获取配置文件中的整形配置项
func GetInt(s string) int {
	return getConfigSource(s).GetInt(s)
}

func GetDuration(s string) time.Duration {
	return getConfigSource(s).GetDuration(s)
}

func GetStringSlice(s string) []string {
	return getConfigSource(s).GetStringSlice(s)
}

func GetBool(s string) bool {
	return getConfigSource(s).GetBool(s)
}

func getConfigSource(s string) *viper.Viper {
	if localConfig.IsSet(s) {
		return localConfig
	}
	return comonConfig
}

// 读取公共配置文件
func loadCommonFileConfig(config *viper.Viper) {
	helper.PrintMsg("load common configuration...")
	path := helper.PwdPath + CONF_PATH
	// 公共配置
	config.SetConfigName(COMMON_CONF)
	config.AddConfigPath(path)
	config.SetConfigType(CONF_TYPE)
	commonErr := config.ReadInConfig()
	if commonErr != nil {
		helper.PrintError(commonErr)
		os.Exit(1)
	} else {
		config.WatchConfig()
		config.OnConfigChange(func(in fsnotify.Event) {
			helper.PrintMsg(in.Name, "common Config file changed")
		})
	}
}

// 读取本地的配置文件
func loadLocalFileConfig(config *viper.Viper) {
	path := helper.PwdPath + CONF_PATH
	// 环境配置
	helper.PrintMsg("load local configuration...")
	config.SetConfigName(GetEnv())
	config.AddConfigPath(path)
	config.SetConfigType(CONF_TYPE)
	err := config.MergeInConfig()
	if err != nil {
		helper.PrintError(err)
		os.Exit(1)
	} else {
		config.WatchConfig()
		config.OnConfigChange(func(in fsnotify.Event) {
			helper.PrintMsg(in.Name, "local Config file changed")
		})
	}
}
