package helper

import (
	"errors"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"strings"
	"time"
)

const FORMAT_TIME = "2006-01-02 15:04:05"

var PwdPath string
var Loc *time.Location

func init() {
	var err error
	PwdPath, err = GetCurrentPath()
	if err != nil {
		fmt.Println(err.Error())
		os.Exit(1)
	}
	Loc, _ = time.LoadLocation("Asia/Shanghai")
}

func GetCurrentPath() (string, error) {
	file, err := exec.LookPath(os.Args[0])
	if err != nil {
		return "", err
	}
	path, err := filepath.Abs(file)
	if err != nil {
		return "", err
	}
	i := strings.LastIndex(path, "/")
	if i < 0 {
		i = strings.LastIndex(path, "\\")
	}
	if i < 0 {
		return "", errors.New(`error: Can't find "/" or "\".`)
	}
	return string(path[0 : i+1]), nil
}

func PrintMsg(msg string, strs ...string) {
	if len(strs) == 0 {
		fmt.Printf("[%s][info][%s][%s]\n", NowInCmt().Format(FORMAT_TIME), FuncCaller(), msg)
		return
	}
	fmt.Printf("[%s][%s][%s][%s]\n", NowInCmt().Format(FORMAT_TIME), strs[0], FuncCaller(), msg)
}

func PrintError(err error) {
	fmt.Printf("[%s][error][%s][%s]\n", NowInCmt().Format(FORMAT_TIME), FuncCaller(), err.Error())
}

func FuncCaller() string {
	funcName, file, line, ok := runtime.Caller(2)
	filePaths := strings.Split(file, "/")
	if len(filePaths) > 2 {
		file = strings.Join(filePaths[len(filePaths)-2:], "/")
	}
	funcPaths := strings.Split(runtime.FuncForPC(funcName).Name(), "/")
	if ok {
		return fmt.Sprintf("caller:%s:%d func:%s", file, line, funcPaths[len(funcPaths)-1])
	}
	return ""
}

func NowInCmt() time.Time {
	return time.Now().In(Loc)
}


